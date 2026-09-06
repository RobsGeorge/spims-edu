<?php

namespace App\Services\Assessment;

use App\Enums\AttemptStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\QuestionType;
use App\Enums\ScoringRule;
use App\Exceptions\ResourceLockedException;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use App\Models\AttemptAnswer;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\User;
use App\Services\Offerings\LearningProgressService;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttemptService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly AssessmentService $assessments,
        private readonly ObjectiveGrader $objective,
        private readonly EssayAiGrader $essayAi,
        private readonly LearningProgressService $progress,
        private readonly ProctorService $proctor,
        private readonly ObjectStorageService $storage,
    ) {}

    public function start(User $student, Assessment $assessment): AssessmentAttempt
    {
        $this->authorize->authorize($student, 'assessments.take');

        $enrolled = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('offering_id', $assessment->offering_id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages(['assessment' => [__('assessment.not_enrolled')]]);
        }

        if (! $assessment->released) {
            throw ValidationException::withMessages(['assessment' => [__('assessment.not_released')]]);
        }

        if (! $assessment->isOpen()) {
            throw ValidationException::withMessages(['assessment' => [__('assessment.window_closed')]]);
        }

        $inProgress = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $student->id)
            ->where('status', AttemptStatus::InProgress)
            ->first();

        if ($inProgress) {
            if ($inProgress->isExpired()) {
                return $this->submit($student, $inProgress, auto: true);
            }

            return $inProgress;
        }

        $used = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $student->id)
            ->count();

        if ($used >= $assessment->attempts_allowed) {
            throw ValidationException::withMessages(['assessment' => [__('assessment.no_attempts')]]);
        }

        $questionIds = $this->assessments->resolveQuestionIds($assessment);
        $startedAt = now();
        $dueAt = $startedAt->copy();
        if ($assessment->time_limit_minutes) {
            $dueAt->addMinutes($assessment->time_limit_minutes);
        } elseif ($assessment->closes_at) {
            $dueAt = $assessment->closes_at->copy();
        } else {
            $dueAt->addHours(24);
        }

        if ($assessment->closes_at && $dueAt->gt($assessment->closes_at)) {
            $dueAt = $assessment->closes_at->copy();
        }

        $questions = Question::query()->with('options')->whereIn('id', $questionIds)->get()->keyBy('id');
        $snapshot = [];
        foreach ($questionIds as $qid) {
            $q = $questions->get($qid);
            if (! $q) {
                continue;
            }
            $options = $q->options->values();
            if ($assessment->shuffle_options) {
                $options = $options->shuffle()->values();
            }
            $snapshot[] = $this->studentSnapshotQuestion($assessment, $q, $options);
        }

        $attempt = AssessmentAttempt::query()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'attempt_no' => $used + 1,
            'started_at' => $startedAt,
            'due_at' => $dueAt,
            'status' => AttemptStatus::InProgress,
            'question_ids' => $questionIds,
            'exam_snapshot' => $snapshot,
            'focus_loss_count' => 0,
        ]);

        $this->audit->write($student, 'assessments.attempt_start', 'AssessmentAttempt', $attempt->id);

        return $attempt;
    }

    /**
     * @param  array<string, array<string, mixed>>  $answers  question_id => response
     */
    public function autosave(User $student, AssessmentAttempt $attempt, array $answers): AssessmentAttempt
    {
        $this->authorize->authorize($student, 'assessments.take');
        $this->assertOwner($student, $attempt);

        if ($attempt->terminated_for_cheating) {
            throw ValidationException::withMessages(['attempt' => [__('assessment.terminated_for_cheating')]]);
        }

        if ($attempt->status !== AttemptStatus::InProgress) {
            throw ValidationException::withMessages(['attempt' => [__('assessment.not_in_progress')]]);
        }

        if ($attempt->isExpired()) {
            return $this->submit($student, $attempt, auto: true);
        }

        DB::transaction(function () use ($attempt, $answers) {
            foreach ($answers as $questionId => $response) {
                if (! in_array($questionId, $attempt->question_ids ?? [], true)) {
                    continue;
                }

                AttemptAnswer::query()->updateOrCreate(
                    ['attempt_id' => $attempt->id, 'question_id' => $questionId],
                    ['response' => $response]
                );
            }
        });

        return $attempt->fresh('answers');
    }

    /**
     * Store a FILE_UPLOAD answer artifact via ObjectStorageService. The path is
     * returned so the runner can persist it on the next autosave.
     *
     * @return array{path: string, filename: string}
     */
    public function uploadFile(User $student, AssessmentAttempt $attempt, UploadedFile $file): array
    {
        $this->authorize->authorize($student, 'assessments.take');
        $this->assertOwner($student, $attempt);

        if ($attempt->status !== AttemptStatus::InProgress) {
            throw ValidationException::withMessages(['attempt' => [__('assessment.not_in_progress')]]);
        }

        if ($attempt->isExpired()) {
            $this->submit($student, $attempt, auto: true);
            throw ValidationException::withMessages(['attempt' => [__('assessment.window_closed')]]);
        }

        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $path = $this->storage->signedUploadPath('submissions', $attempt->id, $extension);
        $filename = $file->getClientOriginalName();

        $this->audit->withAudit($student, 'assessments.attempt_upload', function () use ($file, $path, $attempt) {
            $this->storage->store($path, $file->get() ?: '');

            return $attempt;
        }, 'AssessmentAttempt');

        return [
            'path' => $path,
            'filename' => $filename,
        ];
    }

    /**
     * Delegates to ProctorService so the counter finally does something: enough
     * focus-loss events now escalate to real termination instead of just incrementing a
     * number nobody reads. The public signature and the `focus-loss` route are
     * unchanged, so existing callers keep working.
     */
    public function logFocusLoss(User $student, AssessmentAttempt $attempt): AssessmentAttempt
    {
        return $this->proctor->recordEvent($student, $attempt, 'FOCUS_LOSS');
    }

    public function submit(User $actor, AssessmentAttempt $attempt, bool $auto = false): AssessmentAttempt
    {
        if (! $auto) {
            $this->authorize->authorize($actor, 'assessments.take');
            $this->assertOwner($actor, $attempt);
        }

        if ($attempt->terminated_for_cheating) {
            throw ValidationException::withMessages(['attempt' => [__('assessment.terminated_for_cheating')]]);
        }

        if (in_array($attempt->status, [AttemptStatus::Submitted, AttemptStatus::AutoSubmitted, AttemptStatus::Graded], true)) {
            return $attempt;
        }

        return DB::transaction(function () use ($actor, $attempt, $auto) {
            $attempt = AssessmentAttempt::query()->where('id', $attempt->id)->lockForUpdate()->firstOrFail();
            if ($attempt->status !== AttemptStatus::InProgress) {
                return $attempt;
            }

            $assessment = $attempt->assessment()->firstOrFail();
            $questions = Question::query()->with('options')->whereIn('id', $attempt->question_ids ?? [])->get()->keyBy('id');
            $needsManual = false;
            $total = 0.0;

            foreach ($attempt->question_ids ?? [] as $qid) {
                $question = $questions->get($qid);
                if (! $question) {
                    continue;
                }

                $answer = AttemptAnswer::query()->firstOrCreate(
                    ['attempt_id' => $attempt->id, 'question_id' => $qid],
                    ['response' => null]
                );

                $max = $this->pointsFor($assessment, $question);

                if ($question->type === QuestionType::Essay) {
                    $suggestion = $this->essayAi->suggest($question, (string) ($answer->response['text'] ?? ''), $max);
                    if ($suggestion) {
                        $answer->ai_suggested_score = $suggestion['score'];
                        $answer->ai_rationale = $suggestion['rationale'];
                    }
                    $needsManual = true;
                    $answer->save();

                    continue;
                }

                if ($question->type === QuestionType::FileUpload) {
                    $needsManual = true;
                    $answer->save();

                    continue;
                }

                $score = $this->objective->score($question, $answer->response, $max);
                if ($score === null) {
                    $needsManual = true;
                    $answer->save();

                    continue;
                }

                $answer->auto_score = $score;
                $answer->final_score = $score;
                $answer->graded_at = now();
                $answer->save();
                $total += $score;
            }

            $attempt->update([
                'submitted_at' => now(),
                'status' => $auto ? AttemptStatus::AutoSubmitted : AttemptStatus::Submitted,
                'total_score' => $needsManual ? $total : $total,
            ]);

            if (! $needsManual) {
                $attempt->update(['status' => AttemptStatus::Graded]);
            }

            $this->audit->write($actor, $auto ? 'assessments.auto_submit' : 'assessments.submit', 'AssessmentAttempt', $attempt->id);

            if ($assessment->content_item_id) {
                $item = $assessment->contentItem()->first();
                if ($item) {
                    $this->progress->recordLinkedItemComplete($actor, $item);
                }
            }

            return $attempt->fresh('answers');
        });
    }

    public function overrideScore(User $grader, AttemptAnswer $answer, float $finalScore, ?string $feedback = null): AttemptAnswer
    {
        $this->authorize->authorize($grader, 'assessments.grade', $answer);

        $ownerAttempt = $answer->attempt()->first();
        if ($ownerAttempt?->terminated_for_cheating) {
            throw ValidationException::withMessages([
                'attempt' => [__('assessment.terminated_for_cheating')],
            ]);
        }

        $this->assertGradebookWritable($ownerAttempt);

        $answer->update([
            'final_score' => $finalScore,
            'feedback' => $feedback,
            'graded_by_id' => $grader->id,
            'graded_at' => now(),
        ]);

        $attempt = $answer->attempt()->with('answers')->firstOrFail();
        $total = $attempt->answers->sum(fn (AttemptAnswer $a) => (float) ($a->final_score ?? 0));
        $pending = $attempt->answers->contains(fn (AttemptAnswer $a) => $a->final_score === null);

        $attempt->update([
            'total_score' => $total,
            'status' => $pending ? AttemptStatus::Submitted : AttemptStatus::Graded,
        ]);

        $this->audit->write($grader, 'assessments.grade_override', 'AttemptAnswer', $answer->id);

        return $answer->fresh();
    }

    public function effectiveScore(Assessment $assessment, User $student): ?float
    {
        $attempts = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $student->id)
            ->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::AutoSubmitted, AttemptStatus::Graded])
            ->get();

        return $this->scoreFromAttempts($assessment, $attempts);
    }

    /**
     * Same scoring-rule math as effectiveScore(), from an already-loaded attempt set.
     *
     * @param  Collection<int, AssessmentAttempt>  $attempts
     */
    public function scoreFromAttempts(Assessment $assessment, Collection $attempts): ?float
    {
        if ($attempts->isEmpty()) {
            return null;
        }

        return match ($assessment->scoring_rule) {
            ScoringRule::Latest => (float) $attempts->sortByDesc('attempt_no')->first()->total_score,
            ScoringRule::Average => (float) $attempts->avg('total_score'),
            default => (float) $attempts->max('total_score'),
        };
    }

    public function autoSubmitExpired(): int
    {
        $expired = AssessmentAttempt::query()
            ->where('status', AttemptStatus::InProgress)
            ->where('due_at', '<=', now())
            ->limit(200)
            ->get();

        $count = 0;
        foreach ($expired as $attempt) {
            $student = User::query()->find($attempt->student_id);
            if ($student) {
                $this->submit($student, $attempt, auto: true);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Student-facing snapshot: option text only. Matching choices are a shuffled
     * unique list of keys so the correct pairing is not leaked on each option.
     *
     * @param  Collection<int, \App\Models\QuestionOption>  $options
     * @return array<string, mixed>
     */
    private function studentSnapshotQuestion(Assessment $assessment, Question $question, Collection $options): array
    {
        $entry = [
            'id' => $question->id,
            'type' => $question->type->value,
            'prompt' => $question->prompt,
            'points' => $this->pointsFor($assessment, $question),
            'options' => $options->map(fn ($option) => [
                'id' => $option->id,
                'text' => $option->text,
            ])->all(),
        ];

        if ($question->type === QuestionType::Matching) {
            $entry['match_choices'] = $options
                ->pluck('match_key')
                ->filter()
                ->unique()
                ->shuffle()
                ->values()
                ->all();
        }

        return $entry;
    }

    private function pointsFor(Assessment $assessment, Question $question): float
    {
        $link = AssessmentQuestion::query()
            ->where('assessment_id', $assessment->id)
            ->where('question_id', $question->id)
            ->first();

        return (float) ($link?->points_override ?? $question->points);
    }

    private function assertOwner(User $student, AssessmentAttempt $attempt): void
    {
        if ($attempt->student_id !== $student->id && ! $student->isSuperAdmin()) {
            throw ValidationException::withMessages(['attempt' => [__('assessment.not_owner')]]);
        }
    }

    private function assertGradebookWritable(?AssessmentAttempt $attempt): void
    {
        if ($attempt === null) {
            return;
        }

        $attempt->loadMissing('assessment');
        $offeringId = $attempt->assessment?->offering_id;
        if ($offeringId === null) {
            return;
        }

        $locked = Enrollment::query()
            ->where('offering_id', $offeringId)
            ->where('student_id', $attempt->student_id)
            ->where('grade_status', GradeStatus::Locked)
            ->exists();

        if ($locked) {
            throw new ResourceLockedException(__('assessment.grades_locked'));
        }
    }
}
