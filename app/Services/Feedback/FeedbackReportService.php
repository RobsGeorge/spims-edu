<?php

namespace App\Services\Feedback;

use App\Enums\FeedbackIdentityRevealStatus;
use App\Enums\FeedbackQuestionKind;
use App\Models\FeedbackAnswer;
use App\Models\FeedbackIdentityRevealRequest;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSubmissionIdentity;
use App\Models\FeedbackSurvey;
use App\Models\User;
use App\Support\AuthorizeService;

class FeedbackReportService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
    ) {}

    /**
     * Aggregates by question. Never joins identities.
     *
     * @return list<array<string, mixed>>
     */
    public function aggregatesByQuestion(User $actor, FeedbackSurvey $survey): array
    {
        $this->authorize->authorize($actor, 'feedback.report', $survey);

        $survey->load('questions');
        $answers = FeedbackAnswer::query()
            ->whereHas('submission', fn ($query) => $query->where('survey_id', $survey->id))
            ->get()
            ->groupBy('question_id');

        $out = [];
        foreach ($survey->questions as $question) {
            $questionAnswers = $answers->get($question->id, collect());
            $values = $questionAnswers->pluck('value')->all();
            $out[] = [
                'question_id' => $question->id,
                'prompt' => $question->prompt,
                'kind' => $question->kind->value,
                'response_count' => $questionAnswers->count(),
                'aggregates' => $this->aggregate($question, $values),
            ];
        }

        return $out;
    }

    /**
     * Per-submission rows. student_id is included only when that submission has
     * an APPROVED identity-reveal request — the only path that joins identities.
     *
     * @return list<array<string, mixed>>
     */
    public function submissions(User $actor, FeedbackSurvey $survey): array
    {
        $this->authorize->authorize($actor, 'feedback.report', $survey);

        $submissions = FeedbackSubmission::query()
            ->where('survey_id', $survey->id)
            ->with('answers')
            ->orderBy('submitted_at')
            ->get();

        $approvedIds = FeedbackIdentityRevealRequest::query()
            ->where('status', FeedbackIdentityRevealStatus::Approved)
            ->whereIn('submission_id', $submissions->pluck('id'))
            ->pluck('submission_id')
            ->all();

        $identities = collect();
        if ($approvedIds !== []) {
            $identities = FeedbackSubmissionIdentity::query()
                ->whereIn('submission_id', $approvedIds)
                ->get()
                ->keyBy('submission_id');
        }

        return $submissions->map(function (FeedbackSubmission $submission) use ($approvedIds, $identities) {
            $row = [
                'id' => $submission->id,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'is_anonymous' => $submission->is_anonymous,
                'answers' => $submission->answers
                    ->mapWithKeys(fn (FeedbackAnswer $answer) => [$answer->question_id => $answer->value])
                    ->all(),
            ];

            if (in_array($submission->id, $approvedIds, true)) {
                $identity = $identities->get($submission->id);
                $row['student_id'] = $identity?->getAttribute('student_id');
            }

            return $row;
        })->values()->all();
    }

    /**
     * @param  list<mixed>  $values
     * @return array<string, mixed>
     */
    private function aggregate(FeedbackQuestion $question, array $values): array
    {
        if ($question->kind === FeedbackQuestionKind::Text) {
            return ['responses' => array_values($values)];
        }

        $flat = [];
        foreach ($values as $value) {
            foreach ((array) $value as $item) {
                $flat[] = is_bool($item) ? ($item ? '1' : '0') : (string) $item;
            }
        }

        return ['counts' => $flat === [] ? (object) [] : array_count_values($flat)];
    }
}
