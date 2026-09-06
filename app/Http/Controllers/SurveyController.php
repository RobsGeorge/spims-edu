<?php

namespace App\Http\Controllers;

use App\Models\FeedbackAnswer;
use App\Models\FeedbackSurvey;
use App\Services\Feedback\FeedbackSubmissionService;
use App\Services\Feedback\FeedbackSurveyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SurveyController extends Controller
{
    public function __construct(
        private readonly FeedbackSurveyService $surveys,
        private readonly FeedbackSubmissionService $submissions,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $submitted = array_flip($this->surveys->submittedSurveyIds($user));

        return view('surveys.index', [
            'surveys' => $this->surveys->inboxQuery($user)->with(['questions', 'offering.course'])->get(),
            'submittedIds' => $submitted,
        ]);
    }

    public function show(Request $request, FeedbackSurvey $survey): View
    {
        $user = $request->user();
        $survey = $this->surveys->showFor($user, $survey);
        $survey->loadMissing('offering.course');

        $submissionId = $this->surveys->ownSubmissionId($user, $survey);
        $ownAnswers = [];
        if ($submissionId !== null) {
            $ownAnswers = FeedbackAnswer::query()
                ->where('submission_id', $submissionId)
                ->get()
                ->mapWithKeys(fn (FeedbackAnswer $answer) => [$answer->question_id => $answer->value])
                ->all();
        }

        $submitted = $submissionId !== null;
        $accepting = $survey->isAcceptingSubmissions();

        return view('surveys.show', [
            'survey' => $survey,
            'submitted' => $submitted,
            'accepting' => $accepting,
            'canSubmit' => $accepting && ! $submitted,
            'ownAnswers' => $ownAnswers,
        ]);
    }

    public function submit(Request $request, FeedbackSurvey $survey): RedirectResponse
    {
        $data = $request->validate([
            'answers' => 'nullable|array',
        ]);

        try {
            $this->submissions->submit(
                $request->user(),
                $survey,
                $this->formAnswers($data['answers'] ?? [])
            );
        } catch (ConflictHttpException $e) {
            return redirect()
                ->route('student.surveys.show', $survey)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('student.surveys.show', $survey)
            ->with('status', __('feedback.submitted_thanks'));
    }

    /**
     * Map HTML form fields onto the questionId => value shape the service expects.
     * Empty optional fields are omitted so they are not treated as invalid answers.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function formAnswers(array $answers): array
    {
        $normalized = [];

        foreach ($answers as $questionId => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $normalized[(string) $questionId] = $value;
        }

        return $normalized;
    }
}
