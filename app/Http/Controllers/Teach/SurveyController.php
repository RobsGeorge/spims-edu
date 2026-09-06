<?php

namespace App\Http\Controllers\Teach;

use App\Enums\FeedbackQuestionKind;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teach\Concerns\GuardsTeachOffering;
use App\Models\CourseOffering;
use App\Models\FeedbackSubmission;
use App\Models\FeedbackSurvey;
use App\Services\Feedback\FeedbackIdentityRevealService;
use App\Services\Feedback\FeedbackReportService;
use App\Services\Feedback\FeedbackSurveyService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SurveyController extends Controller
{
    use GuardsTeachOffering;

    public function __construct(
        private readonly FeedbackSurveyService $surveys,
        private readonly FeedbackReportService $reports,
        private readonly FeedbackIdentityRevealService $reveals,
        private readonly AuthorizeService $authorize,
    ) {}

    public function index(Request $request, CourseOffering $offering): View
    {
        $this->guardTeach($request, $offering);
        $this->authorize->authorize($request->user(), 'feedback.manage', $offering);

        $items = FeedbackSurvey::query()
            ->where('offering_id', $offering->id)
            ->withCount('questions')
            ->orderByDesc('created_at')
            ->get();

        return view('staff.surveys.index', [
            'offering' => $offering->load('course'),
            'surveys' => $items,
            'kinds' => FeedbackQuestionKind::cases(),
            'storeRoute' => route('teach.surveys.store', $offering),
            'showRoute' => fn (FeedbackSurvey $survey) => route('teach.surveys.show', [$offering, $survey]),
        ]);
    }

    public function store(Request $request, CourseOffering $offering): RedirectResponse
    {
        $this->guardTeach($request, $offering);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'anonymous_default' => 'nullable|boolean',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after:opens_at',
        ]);

        $survey = $this->surveys->create($request->user(), [
            'title' => $data['title'],
            'anonymous_default' => $request->boolean('anonymous_default', true),
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
        ], $offering);

        return redirect()
            ->route('teach.surveys.show', [$offering, $survey])
            ->with('status', __('staff.surveys.created'));
    }

    public function show(Request $request, CourseOffering $offering, FeedbackSurvey $survey): View
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingSurvey($offering, $survey);
        $this->authorize->authorize($request->user(), 'feedback.manage', $survey);

        return view('staff.surveys.show', [
            'offering' => $offering->load('course'),
            'survey' => $survey->load('questions'),
            'kinds' => FeedbackQuestionKind::cases(),
            'questionRoute' => route('teach.surveys.questions.store', [$offering, $survey]),
            'publishRoute' => route('teach.surveys.publish', [$offering, $survey]),
            'closeRoute' => route('teach.surveys.close', [$offering, $survey]),
            'reportRoute' => route('teach.surveys.report', [$offering, $survey]),
            'backRoute' => route('teach.surveys.index', $offering),
        ]);
    }

    public function addQuestion(Request $request, CourseOffering $offering, FeedbackSurvey $survey): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingSurvey($offering, $survey);

        $data = $request->validate([
            'prompt' => 'required|string|max:2000',
            'kind' => 'required|in:TEXT,SINGLE,MULTI,SCALE',
            'options_text' => 'nullable|string',
            'required' => 'nullable|boolean',
        ]);

        $options = $this->parseOptions($data['kind'], $data['options_text'] ?? null);

        $this->surveys->addQuestion($request->user(), $survey, [
            'prompt' => $data['prompt'],
            'kind' => $data['kind'],
            'options' => $options,
            'required' => $request->boolean('required', true),
        ]);

        return back()->with('status', __('staff.surveys.question_added'));
    }

    public function publish(Request $request, CourseOffering $offering, FeedbackSurvey $survey): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingSurvey($offering, $survey);

        $this->surveys->publish($request->user(), $survey);

        return back()->with('status', __('staff.surveys.published'));
    }

    public function close(Request $request, CourseOffering $offering, FeedbackSurvey $survey): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingSurvey($offering, $survey);

        $this->surveys->close($request->user(), $survey);

        return back()->with('status', __('staff.surveys.closed'));
    }

    public function report(Request $request, CourseOffering $offering, FeedbackSurvey $survey): View
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingSurvey($offering, $survey);

        $aggregates = $this->reports->aggregatesByQuestion($request->user(), $survey);
        $submissions = $this->reports->submissions($request->user(), $survey);
        $rows = array_map(function (array $row) {
            unset($row['student_id']);

            return $row;
        }, $submissions);

        return view('staff.surveys.report', [
            'offering' => $offering->load('course'),
            'survey' => $survey->load('questions'),
            'aggregates' => $aggregates,
            'submissions' => $rows,
            'revealRoute' => fn (string $id) => route('teach.surveys.reveals.store', [$offering, $survey, $id]),
            'backRoute' => route('teach.surveys.show', [$offering, $survey]),
        ]);
    }

    public function requestReveal(
        Request $request,
        CourseOffering $offering,
        FeedbackSurvey $survey,
        FeedbackSubmission $submission,
    ): RedirectResponse {
        $this->guardTeach($request, $offering);
        $this->assertOfferingSurvey($offering, $survey);
        abort_unless($submission->survey_id === $survey->id, 404);

        $data = $request->validate([
            'reason' => 'nullable|string|max:2000',
        ]);

        try {
            $this->reveals->request($request->user(), $submission, $data['reason'] ?? null);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('staff.surveys.reveal_requested'));
    }

    private function assertOfferingSurvey(CourseOffering $offering, FeedbackSurvey $survey): void
    {
        abort_unless($survey->offering_id === $offering->id, 404);
    }

    /**
     * @return list<string>|null
     */
    private function parseOptions(string $kind, ?string $text): ?array
    {
        if (! in_array($kind, [FeedbackQuestionKind::Single->value, FeedbackQuestionKind::Multi->value], true)) {
            return null;
        }

        $lines = preg_split('/[\r\n,]+/', (string) $text) ?: [];
        $options = array_values(array_filter(array_map('trim', $lines), fn (string $line) => $line !== ''));

        return $options === [] ? null : $options;
    }
}
