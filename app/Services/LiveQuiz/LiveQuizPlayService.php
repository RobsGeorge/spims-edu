<?php

namespace App\Services\LiveQuiz;

use App\Enums\EnrollmentStatus;
use App\Enums\LiveQuizSessionState;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Models\Enrollment;
use App\Models\LiveQuizAnswer;
use App\Models\LiveQuizOption;
use App\Models\LiveQuizParticipant;
use App\Models\LiveQuizQuestion;
use App\Models\LiveQuizSession;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LiveQuizPlayService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function join(User $actor, string $code): LiveQuizParticipant
    {
        $this->authorize->authorize($actor, 'live_quiz.play');

        $session = LiveQuizSession::query()
            ->where('join_code', $this->normalizeCode($code))
            ->first();

        if ($session === null) {
            throw (new ModelNotFoundException)->setModel(LiveQuizSession::class);
        }

        $session->load('quiz');

        if ($session->state === LiveQuizSessionState::Ended) {
            throw new ConflictException(__('live_quiz.join_closed'));
        }

        $this->assertEnrolled($actor, $session->quiz->offering_id);

        $existing = LiveQuizParticipant::query()
            ->where('session_id', $session->id)
            ->where('student_id', $actor->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->audit->withAudit($actor, 'live_quiz.join', function () use ($actor, $session) {
            return LiveQuizParticipant::query()->create([
                'session_id' => $session->id,
                'student_id' => $actor->id,
                'display_name' => trim($actor->first_name.' '.$actor->last_name),
                'joined_at' => now(),
            ]);
        }, 'LiveQuizParticipant');
    }

    /**
     * Read-only snapshot. Must not write — this is the polling fallback when
     * broadcasting is off (BROADCAST_DRIVER=null).
     *
     * @return array<string, mixed>
     */
    public function poll(User $actor, LiveQuizSession $session): array
    {
        $this->authorize->authorize($actor, 'live_quiz.play');

        $participant = $this->participantFor($actor, $session);
        if ($participant === null) {
            throw new AuthorizationException(__('live_quiz.not_a_participant'));
        }

        $session->load(['quiz', 'currentQuestion.options']);

        return $this->snapshot($session, $participant);
    }

    public function answer(
        User $actor,
        LiveQuizSession $session,
        LiveQuizQuestion $question,
        string $optionId,
    ): LiveQuizAnswer {
        $this->authorize->authorize($actor, 'live_quiz.play');

        $participant = $this->participantFor($actor, $session);
        if ($participant === null) {
            throw new AuthorizationException(__('live_quiz.not_a_participant'));
        }

        return $this->audit->withAudit($actor, 'live_quiz.answer', function () use ($session, $question, $optionId, $participant) {
            /** @var LiveQuizSession $locked */
            $locked = LiveQuizSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($locked->state !== LiveQuizSessionState::QuestionOpen) {
                throw new ConflictException(__('live_quiz.not_accepting_answers'));
            }

            if ($question->quiz_id !== $locked->quiz_id || $locked->current_question_id !== $question->id) {
                throw new ConflictException(__('live_quiz.question_not_open'));
            }

            if (LiveQuizAnswer::query()
                ->where('session_id', $locked->id)
                ->where('question_id', $question->id)
                ->where('participant_id', $participant->id)
                ->exists()) {
                throw new ConflictException(__('live_quiz.already_answered'));
            }

            $option = LiveQuizOption::query()
                ->whereKey($optionId)
                ->where('question_id', $question->id)
                ->first();

            if ($option === null) {
                throw (new ModelNotFoundException)->setModel(LiveQuizOption::class);
            }

            $answeredAt = now();
            $score = $this->scoreFor($question, $option, $locked, $answeredAt);

            return LiveQuizAnswer::query()->create([
                'session_id' => $locked->id,
                'question_id' => $question->id,
                'participant_id' => $participant->id,
                'option_id' => $option->id,
                'answered_at' => $answeredAt,
                'score' => $score,
            ]);
        }, 'LiveQuizAnswer');
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(LiveQuizSession $session, ?LiveQuizParticipant $participant = null): array
    {
        $includeCorrectness = in_array($session->state, [
            LiveQuizSessionState::Results,
            LiveQuizSessionState::Ended,
        ], true);

        $question = $session->currentQuestion;
        $yourAnswer = null;
        if ($participant !== null && $question !== null) {
            $yourAnswer = LiveQuizAnswer::query()
                ->where('session_id', $session->id)
                ->where('question_id', $question->id)
                ->where('participant_id', $participant->id)
                ->first();
        }

        return [
            'id' => $session->id,
            'join_code' => $session->join_code,
            'state' => $session->state->value,
            'server_now' => now()->toIso8601String(),
            'quiz' => [
                'id' => $session->quiz_id,
                'title' => $session->quiz?->title,
            ],
            'current_question' => $question === null ? null : [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'position' => $question->position,
                'time_limit_seconds' => $question->time_limit_seconds,
                'points' => $question->points,
                'opened_at' => $session->question_opened_at?->toIso8601String(),
                'closes_at' => $session->question_closes_at?->toIso8601String(),
                'options' => $question->options->map(function (LiveQuizOption $option) use ($includeCorrectness) {
                    $row = [
                        'id' => $option->id,
                        'label' => $option->label,
                        'position' => $option->position,
                    ];
                    if ($includeCorrectness) {
                        $row['is_correct'] = $option->is_correct;
                    }

                    return $row;
                })->values()->all(),
            ],
            'you' => $participant === null ? null : [
                'participant_id' => $participant->id,
                'display_name' => $participant->display_name,
                'score' => $yourAnswer?->score,
                'answered' => $yourAnswer !== null,
            ],
        ];
    }

    private function scoreFor(
        LiveQuizQuestion $question,
        LiveQuizOption $option,
        LiveQuizSession $session,
        \DateTimeInterface $answeredAt,
    ): int {
        $closesAt = $session->question_closes_at;
        if ($closesAt !== null && $answeredAt->getTimestamp() > $closesAt->getTimestamp()) {
            return 0;
        }

        if (! $option->is_correct) {
            return 0;
        }

        $remaining = 0;
        if ($closesAt !== null) {
            $remaining = max(0, $closesAt->getTimestamp() - $answeredAt->getTimestamp());
        }

        return LiveQuizScoring::correctScore(
            $question->points,
            $question->time_limit_seconds,
            $remaining,
        );
    }

    private function participantFor(User $actor, LiveQuizSession $session): ?LiveQuizParticipant
    {
        return LiveQuizParticipant::query()
            ->where('session_id', $session->id)
            ->where('student_id', $actor->id)
            ->first();
    }

    private function assertEnrolled(User $actor, string $offeringId): void
    {
        $enrolled = Enrollment::query()
            ->where('student_id', $actor->id)
            ->where('offering_id', $offeringId)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->exists();

        if (! $enrolled) {
            throw new AuthorizationException(__('live_quiz.not_enrolled'));
        }
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
    }
}
