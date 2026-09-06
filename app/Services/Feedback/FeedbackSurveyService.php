<?php

namespace App\Services\Feedback;

use App\Enums\FeedbackQuestionKind;
use App\Enums\FeedbackSurveyStatus;
use App\Models\CourseOffering;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmissionIdentity;
use App\Models\FeedbackSurvey;
use App\Models\User;
use App\Services\Learning\OfferingAccessService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FeedbackSurveyService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly OfferingAccessService $access,
    ) {}

    /**
     * @param  array{title: string, anonymous_default?: bool, opens_at?: mixed, closes_at?: mixed}  $data
     */
    public function create(User $actor, array $data, ?CourseOffering $offering = null): FeedbackSurvey
    {
        $this->authorize->authorize($actor, 'feedback.manage', $offering);

        return $this->audit->withAudit($actor, 'feedback.create', function () use ($actor, $data, $offering) {
            return FeedbackSurvey::query()->create([
                'offering_id' => $offering?->id,
                'title' => $data['title'],
                'status' => FeedbackSurveyStatus::Draft,
                'anonymous_default' => (bool) ($data['anonymous_default'] ?? true),
                'opens_at' => $data['opens_at'] ?? null,
                'closes_at' => $data['closes_at'] ?? null,
                'created_by' => $actor->id,
            ]);
        }, FeedbackSurvey::class);
    }

    /**
     * @param  array{prompt: string, kind: string, options?: ?array, position?: ?int, required?: bool}  $data
     */
    public function addQuestion(User $actor, FeedbackSurvey $survey, array $data): FeedbackQuestion
    {
        $this->authorize->authorize($actor, 'feedback.manage', $survey);

        if (! $survey->isDraft()) {
            throw ValidationException::withMessages([
                'status' => [__('feedback.questions_locked')],
            ]);
        }

        $kind = FeedbackQuestionKind::from($data['kind']);
        $options = $data['options'] ?? null;
        if (in_array($kind, [FeedbackQuestionKind::Single, FeedbackQuestionKind::Multi], true)
            && (! is_array($options) || $options === [])) {
            throw ValidationException::withMessages([
                'options' => [__('feedback.options_required')],
            ]);
        }

        return $this->audit->withAudit($actor, 'feedback.question.add', function () use ($survey, $data, $kind, $options) {
            $position = $data['position'] ?? ((int) $survey->questions()->max('position') + 1);

            return FeedbackQuestion::query()->create([
                'survey_id' => $survey->id,
                'prompt' => $data['prompt'],
                'kind' => $kind,
                'options' => $options,
                'position' => $position,
                'required' => (bool) ($data['required'] ?? true),
            ]);
        }, FeedbackQuestion::class);
    }

    public function publish(User $actor, FeedbackSurvey $survey): FeedbackSurvey
    {
        $this->authorize->authorize($actor, 'feedback.manage', $survey);

        if (! $survey->isDraft()) {
            throw ValidationException::withMessages([
                'status' => [__('feedback.already_published')],
            ]);
        }

        if ($survey->questions()->count() === 0) {
            throw ValidationException::withMessages([
                'questions' => [__('feedback.questions_required')],
            ]);
        }

        return $this->audit->withAudit($actor, 'feedback.publish', function () use ($survey) {
            $survey->status = FeedbackSurveyStatus::Published;
            $survey->save();

            return $survey->fresh('questions');
        }, FeedbackSurvey::class);
    }

    public function close(User $actor, FeedbackSurvey $survey): FeedbackSurvey
    {
        $this->authorize->authorize($actor, 'feedback.manage', $survey);

        if (! $survey->isPublished()) {
            throw ValidationException::withMessages([
                'status' => [__('feedback.not_published')],
            ]);
        }

        return $this->audit->withAudit($actor, 'feedback.close', function () use ($survey) {
            $survey->status = FeedbackSurveyStatus::Closed;
            $survey->save();

            return $survey->fresh('questions');
        }, FeedbackSurvey::class);
    }

    public function inboxQuery(User $student): Builder
    {
        $this->authorize->authorize($student, 'feedback.view');

        $offeringIds = $this->access->enrolledOfferingIds($student);

        return FeedbackSurvey::query()
            ->whereIn('status', [
                FeedbackSurveyStatus::Published->value,
                FeedbackSurveyStatus::Closed->value,
            ])
            ->where(function (Builder $query) use ($offeringIds) {
                $query->whereNull('offering_id')
                    ->orWhereIn('offering_id', $offeringIds);
            })
            ->orderByDesc('created_at');
    }

    /**
     * @return list<FeedbackSurvey>
     */
    public function inboxFor(User $student): array
    {
        return $this->inboxQuery($student)->with('questions')->get()->all();
    }

    public function showFor(User $student, FeedbackSurvey $survey): FeedbackSurvey
    {
        $this->authorize->authorize($student, 'feedback.view');
        $this->assertStudentMayRead($student, $survey);

        return $survey->load('questions');
    }

    /**
     * Draft / unpublished / out-of-scope surveys are indistinguishable: 404.
     */
    public function assertStudentMayRead(User $student, FeedbackSurvey $survey): void
    {
        if (! $this->studentMayRead($student, $survey)) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * Offering-scoped surveys the student is not enrolled in are 404 even on write
     * (existence is private). Unpublished/closed/window failures on submit are 422
     * and are checked separately.
     */
    public function assertStudentMayReach(User $student, FeedbackSurvey $survey): void
    {
        $this->authorize->authorize($student, 'feedback.view');

        if ($survey->offering_id !== null && $this->access->enrollmentFor($student, $survey->offering) === null) {
            throw new NotFoundHttpException;
        }
    }

    public function studentMayRead(User $student, FeedbackSurvey $survey): bool
    {
        if ($survey->isDraft()) {
            return false;
        }

        if ($survey->offering_id === null) {
            return true;
        }

        return $this->access->enrollmentFor($student, $survey->offering) !== null;
    }

    /**
     * @return list<string>
     */
    public function submittedSurveyIds(User $student): array
    {
        return FeedbackSubmissionIdentity::query()
            ->where('student_id', $student->id)
            ->whereHas('submission')
            ->with('submission:id,survey_id')
            ->get()
            ->map(fn (FeedbackSubmissionIdentity $identity) => $identity->submission?->survey_id)
            ->filter()
            ->values()
            ->all();
    }

    public function ownSubmissionId(User $student, FeedbackSurvey $survey): ?string
    {
        $identity = FeedbackSubmissionIdentity::query()
            ->where('student_id', $student->id)
            ->whereHas('submission', fn (Builder $query) => $query->where('survey_id', $survey->id))
            ->first();

        return $identity?->submission_id;
    }
}
