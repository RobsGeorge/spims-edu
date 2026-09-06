<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FeedbackAnswer;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Services\Feedback\FeedbackSubmissionService;
use App\Services\Feedback\FeedbackSurveyService;
use App\Support\Api\PaginatedEnvelope;
use App\Support\Api\StudentPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackSurveyController extends Controller
{
    public function __construct(
        private readonly FeedbackSurveyService $surveys,
        private readonly FeedbackSubmissionService $submissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);
        $page = $this->surveys->inboxQuery($request->user())->paginate($perPage);
        $submitted = array_flip($this->surveys->submittedSurveyIds($request->user()));

        $page->setCollection($page->getCollection()->map(
            fn (FeedbackSurvey $survey) => $this->listPayload($survey, isset($submitted[$survey->id]))
        ));

        return response()->json(PaginatedEnvelope::from($page));
    }

    public function show(Request $request, FeedbackSurvey $feedbackSurvey): JsonResponse
    {
        $survey = $this->surveys->showFor($request->user(), $feedbackSurvey);
        $submissionId = $this->surveys->ownSubmissionId($request->user(), $survey);
        $ownAnswers = [];
        if ($submissionId !== null) {
            $ownAnswers = FeedbackAnswer::query()
                ->where('submission_id', $submissionId)
                ->get()
                ->mapWithKeys(fn (FeedbackAnswer $answer) => [$answer->question_id => $answer->value])
                ->all();
        }

        return response()->json(['data' => $this->showPayload($survey, $submissionId !== null, $ownAnswers)]);
    }

    public function submit(Request $request, FeedbackSurvey $feedbackSurvey): JsonResponse
    {
        $data = $request->validate([
            'answers' => 'required|array',
        ]);

        $submission = $this->submissions->submit($request->user(), $feedbackSurvey, $data['answers']);

        return response()->json(['data' => $this->submissionPayload($submission)], 201);
    }

    /** @return array<string, mixed> */
    private function listPayload(FeedbackSurvey $survey, bool $submitted): array
    {
        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'status' => $survey->status->value,
            'status_label' => $survey->statusLabel(),
            'offering_id' => $survey->offering_id,
            'anonymous_default' => $survey->anonymous_default,
            'opens_at' => StudentPayload::iso($survey->opens_at),
            'closes_at' => StudentPayload::iso($survey->closes_at),
            'submitted' => $submitted,
        ];
    }

    /**
     * @param  array<string, mixed>  $ownAnswers
     * @return array<string, mixed>
     */
    private function showPayload(FeedbackSurvey $survey, bool $submitted, array $ownAnswers): array
    {
        $data = $this->listPayload($survey, $submitted);
        $data['questions'] = $survey->questions->map(fn (FeedbackQuestion $question) => [
            'id' => $question->id,
            'prompt' => $question->prompt,
            'kind' => $question->kind->value,
            'options' => $question->options,
            'position' => $question->position,
            'required' => $question->required,
        ])->values()->all();
        $data['answers'] = $ownAnswers;

        return $data;
    }

    /** @return array<string, mixed> */
    private function submissionPayload(FeedbackSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'survey_id' => $submission->survey_id,
            'submitted_at' => StudentPayload::iso($submission->submitted_at),
            'is_anonymous' => $submission->is_anonymous,
            'answers' => $submission->answers
                ->mapWithKeys(fn (FeedbackAnswer $answer) => [$answer->question_id => $answer->value])
                ->all(),
        ];
    }
}
