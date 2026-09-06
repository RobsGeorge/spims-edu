<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeedbackQuestionKind;
use App\Http\Controllers\Controller;
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
    public function __construct(
        private readonly FeedbackSurveyService $surveys,
        private readonly FeedbackReportService $reports,
        private readonly FeedbackIdentityRevealService $reveals,
        private readonly AuthorizeService $authorize,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize->authorize($request->user(), 'feedback.manage');

        $items = FeedbackSurvey::query()
            ->whereNull('offering_id')
            ->withCount('questions')
            ->orderByDesc('created_at')
            ->get();

        return view('staff.surveys.index', [
            'offering' => null,
            'surveys' => $items,
            'kinds' => FeedbackQuestionKind::cases(),
            'storeRoute' => route('admin.surveys.store'),
            'showRoute' => fn (FeedbackSurvey $survey) => route('admin.surveys.show', $survey),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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
        ], null);

        return redirect()
            ->route('admin.surveys.show', $survey)
            ->with('status', __('staff.surveys.created'));
    }

    public function show(Request $request, FeedbackSurvey $survey): View
    {
        $this->assertSchoolWide($survey);
        $this->authorize->authorize($request->user(), 'feedback.manage', $survey);

        return view('staff.surveys.show', [
            'offering' => null,
            'survey' => $survey->load('questions'),
            'kinds' => FeedbackQuestionKind::cases(),
            'questionRoute' => route('admin.surveys.questions.store', $survey),
            'publishRoute' => route('admin.surveys.publish', $survey),
            'closeRoute' => route('admin.surveys.close', $survey),
            'reportRoute' => route('admin.surveys.report', $survey),
            'backRoute' => route('admin.surveys.index'),
        ]);
    }

    public function addQuestion(Request $request, FeedbackSurvey $survey): RedirectResponse
    {
        $this->assertSchoolWide($survey);

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

    public function publish(Request $request, FeedbackSurvey $survey): RedirectResponse
    {
        $this->assertSchoolWide($survey);
        $this->surveys->publish($request->user(), $survey);

        return back()->with('status', __('staff.surveys.published'));
    }

    public function close(Request $request, FeedbackSurvey $survey): RedirectResponse
    {
        $this->assertSchoolWide($survey);
        $this->surveys->close($request->user(), $survey);

        return back()->with('status', __('staff.surveys.closed'));
    }

    public function report(Request $request, FeedbackSurvey $survey): View
    {
        $this->assertSchoolWide($survey);

        $aggregates = $this->reports->aggregatesByQuestion($request->user(), $survey);
        $submissions = $this->reports->submissions($request->user(), $survey);
        $rows = array_map(function (array $row) {
            unset($row['student_id']);

            return $row;
        }, $submissions);

        return view('staff.surveys.report', [
            'offering' => null,
            'survey' => $survey->load('questions'),
            'aggregates' => $aggregates,
            'submissions' => $rows,
            'revealRoute' => fn (string $id) => route('admin.surveys.reveals.store', [$survey, $id]),
            'backRoute' => route('admin.surveys.show', $survey),
        ]);
    }

    public function requestReveal(
        Request $request,
        FeedbackSurvey $survey,
        FeedbackSubmission $submission,
    ): RedirectResponse {
        $this->assertSchoolWide($survey);
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

    private function assertSchoolWide(FeedbackSurvey $survey): void
    {
        abort_unless($survey->offering_id === null, 404);
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
