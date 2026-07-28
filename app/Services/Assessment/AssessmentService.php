<?php

namespace App\Services\Assessment;

use App\Enums\AssessmentMode;
use App\Enums\ResultsVisibility;
use App\Enums\ScoringRule;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\CourseOffering;
use App\Models\Question;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, CourseOffering $offering, array $data): Assessment
    {
        $this->authorize->authorize($actor, 'assessments.manage');

        return $this->audit->withAudit($actor, 'assessments.create', function () use ($offering, $data) {
            return Assessment::query()->create([
                'offering_id' => $offering->id,
                'content_item_id' => $data['content_item_id'] ?? null,
                'component_id' => $data['component_id'] ?? null,
                'mode' => AssessmentMode::from($data['mode'] ?? AssessmentMode::Exam->value),
                'title' => $data['title'],
                'language' => $data['language'] ?? 'en',
                'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
                'opens_at' => $data['opens_at'] ?? null,
                'closes_at' => $data['closes_at'] ?? null,
                'attempts_allowed' => $data['attempts_allowed'] ?? 1,
                'scoring_rule' => ScoringRule::from($data['scoring_rule'] ?? ScoringRule::Highest->value),
                'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? true),
                'shuffle_options' => (bool) ($data['shuffle_options'] ?? true),
                'draw_from_bank_id' => $data['draw_from_bank_id'] ?? null,
                'questions_to_draw' => $data['questions_to_draw'] ?? null,
                'results_visibility' => ResultsVisibility::from($data['results_visibility'] ?? ResultsVisibility::OnRelease->value),
                'reveal_answers' => (bool) ($data['reveal_answers'] ?? false),
                'enforce_full_screen' => (bool) ($data['enforce_full_screen'] ?? false),
                'one_at_a_time' => (bool) ($data['one_at_a_time'] ?? false),
                'no_backtrack' => (bool) ($data['no_backtrack'] ?? false),
                'log_focus_loss' => (bool) ($data['log_focus_loss'] ?? true),
                'max_points' => $data['max_points'] ?? 100,
                'item_weight' => $data['item_weight'] ?? null,
                'released' => false,
            ]);
        }, 'Assessment');
    }

    public function attachQuestion(User $actor, Assessment $assessment, Question $question, ?float $pointsOverride = null, ?int $order = null): AssessmentQuestion
    {
        $this->authorize->authorize($actor, 'assessments.manage');

        return AssessmentQuestion::query()->updateOrCreate(
            ['assessment_id' => $assessment->id, 'question_id' => $question->id],
            [
                'order' => $order ?? ((int) $assessment->assessmentQuestions()->max('order') + 1),
                'points_override' => $pointsOverride,
            ]
        );
    }

    public function release(User $actor, Assessment $assessment): Assessment
    {
        $this->authorize->authorize($actor, 'assessments.manage');

        $assessment->update(['released' => true]);
        $this->audit->write($actor, 'assessments.release', 'Assessment', $assessment->id);

        return $assessment->fresh();
    }

    /**
     * @return list<string>
     */
    public function resolveQuestionIds(Assessment $assessment): array
    {
        if ($assessment->draw_from_bank_id && $assessment->questions_to_draw) {
            $ids = Question::query()
                ->where('bank_id', $assessment->draw_from_bank_id)
                ->pluck('id')
                ->all();

            if (count($ids) < $assessment->questions_to_draw) {
                throw ValidationException::withMessages(['bank' => [__('assessment.bank_too_small')]]);
            }

            shuffle($ids);

            return array_values(array_slice($ids, 0, $assessment->questions_to_draw));
        }

        $ids = $assessment->assessmentQuestions()->orderBy('order')->pluck('question_id')->all();

        if ($assessment->shuffle_questions) {
            shuffle($ids);
        }

        return array_values($ids);
    }
}
