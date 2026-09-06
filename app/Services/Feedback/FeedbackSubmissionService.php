<?php

namespace App\Services\Feedback;

use App\Enums\FeedbackQuestionKind;
use App\Models\FeedbackAnswer;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSubmissionIdentity;
use App\Models\FeedbackSurvey;
use App\Models\User;
use App\Support\AuditLogWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FeedbackSubmissionService
{
    public function __construct(
        private readonly AuditLogWriter $audit,
        private readonly FeedbackSurveyService $surveys,
    ) {}

    /**
     * @param  array<string, mixed>  $answers  questionId => value
     */
    public function submit(User $student, FeedbackSurvey $survey, array $answers): FeedbackSubmission
    {
        $this->surveys->assertStudentMayReach($student, $survey);

        if (! $survey->isAcceptingSubmissions()) {
            throw ValidationException::withMessages([
                'survey' => [$this->closedReason($survey)],
            ]);
        }

        $normalized = $this->validatedAnswers($survey, $answers);

        return $this->audit->withAudit($student, 'feedback.submit', function () use ($student, $survey, $normalized) {
            FeedbackSurvey::query()->whereKey($survey->id)->lockForUpdate()->firstOrFail();

            $already = FeedbackSubmissionIdentity::query()
                ->where('student_id', $student->id)
                ->whereHas('submission', fn (Builder $query) => $query->where('survey_id', $survey->id))
                ->exists();

            if ($already) {
                throw new ConflictHttpException(__('feedback.already_submitted'));
            }

            $submission = FeedbackSubmission::query()->create([
                'survey_id' => $survey->id,
                'submitted_at' => now(),
                'is_anonymous' => true,
            ]);

            FeedbackSubmissionIdentity::query()->create([
                'submission_id' => $submission->id,
                'student_id' => $student->id,
            ]);

            foreach ($normalized as $questionId => $value) {
                FeedbackAnswer::query()->create([
                    'submission_id' => $submission->id,
                    'question_id' => $questionId,
                    'value' => $value,
                ]);
            }

            return $submission->load('answers');
        }, FeedbackSubmission::class);
    }

    private function closedReason(FeedbackSurvey $survey): string
    {
        if ($survey->isClosed()) {
            return __('feedback.closed');
        }

        if (! $survey->isPublished()) {
            return __('feedback.unpublished');
        }

        return __('feedback.outside_window');
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function validatedAnswers(FeedbackSurvey $survey, array $answers): array
    {
        $questions = $survey->questions()->get()->keyBy('id');
        $normalized = [];

        foreach ($questions as $id => $question) {
            $present = array_key_exists($id, $answers);
            if ($question->required && ! $present) {
                throw ValidationException::withMessages([
                    'answers.'.$id => [__('feedback.answer_required')],
                ]);
            }
            if (! $present) {
                continue;
            }
            $normalized[$id] = $this->assertValue($question, $answers[$id]);
        }

        foreach ($answers as $id => $value) {
            if (! $questions->has($id)) {
                throw ValidationException::withMessages([
                    'answers.'.$id => [__('feedback.unknown_question')],
                ]);
            }
        }

        return $normalized;
    }

    private function assertValue(FeedbackQuestion $question, mixed $value): mixed
    {
        return match ($question->kind) {
            FeedbackQuestionKind::Text => $this->textValue($question, $value),
            FeedbackQuestionKind::Single => $this->choiceValue($question, $value, multi: false),
            FeedbackQuestionKind::Multi => $this->choiceValue($question, $value, multi: true),
            FeedbackQuestionKind::Scale => $this->scaleValue($question, $value),
        };
    }

    private function textValue(FeedbackQuestion $question, mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages([
                'answers.'.$question->id => [__('feedback.invalid_answer')],
            ]);
        }

        return $value;
    }

    private function choiceValue(FeedbackQuestion $question, mixed $value, bool $multi): array|string
    {
        $options = array_map('strval', $question->options ?? []);

        if ($multi) {
            if (! is_array($value) || $value === []) {
                throw ValidationException::withMessages([
                    'answers.'.$question->id => [__('feedback.invalid_answer')],
                ]);
            }
            $picked = array_map('strval', $value);
            foreach ($picked as $item) {
                if (! in_array($item, $options, true)) {
                    throw ValidationException::withMessages([
                        'answers.'.$question->id => [__('feedback.invalid_answer')],
                    ]);
                }
            }

            return array_values($picked);
        }

        $picked = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
        if ($picked === '' || ! in_array($picked, $options, true)) {
            throw ValidationException::withMessages([
                'answers.'.$question->id => [__('feedback.invalid_answer')],
            ]);
        }

        return $picked;
    }

    private function scaleValue(FeedbackQuestion $question, mixed $value): int
    {
        $min = 1;
        $max = 5;
        $options = $question->options ?? [];
        if (isset($options['min'], $options['max'])) {
            $min = (int) $options['min'];
            $max = (int) $options['max'];
        } elseif (is_array($options) && $options !== [] && array_is_list($options)) {
            $numeric = array_map('intval', $options);
            $min = min($numeric);
            $max = max($numeric);
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'answers.'.$question->id => [__('feedback.invalid_answer')],
            ]);
        }

        $int = (int) $value;
        if ($int < $min || $int > $max) {
            throw ValidationException::withMessages([
                'answers.'.$question->id => [__('feedback.invalid_answer')],
            ]);
        }

        return $int;
    }
}
