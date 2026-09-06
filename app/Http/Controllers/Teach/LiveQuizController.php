<?php

namespace App\Http\Controllers\Teach;

use App\Enums\LiveQuizSessionState;
use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teach\Concerns\GuardsTeachOffering;
use App\Models\CourseOffering;
use App\Models\LiveQuiz;
use App\Models\LiveQuizQuestion;
use App\Models\LiveQuizSession;
use App\Services\LiveQuiz\LiveQuizHostService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveQuizController extends Controller
{
    use GuardsTeachOffering;

    public function __construct(
        private readonly LiveQuizHostService $host,
        private readonly AuthorizeService $authorize,
    ) {}

    public function index(Request $request, CourseOffering $offering): View
    {
        $this->guardTeach($request, $offering);
        $this->authorize->authorize($request->user(), 'live_quiz.manage', $offering);

        $quizzes = LiveQuiz::query()
            ->where('offering_id', $offering->id)
            ->with(['questions.options', 'sessions' => fn ($q) => $q->orderByDesc('created_at')])
            ->orderByDesc('created_at')
            ->get();

        return view('teach.live-quiz.index', [
            'offering' => $offering->load('course'),
            'quizzes' => $quizzes,
        ]);
    }

    public function store(Request $request, CourseOffering $offering): RedirectResponse
    {
        $this->guardTeach($request, $offering);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'prompt' => 'nullable|string|max:2000',
            'time_limit_seconds' => 'nullable|integer|min:1|max:600',
            'points' => 'nullable|integer|min:1|max:100000',
            'options' => 'nullable|array|min:2',
            'options.*' => 'nullable|string|max:500',
            'correct_index' => 'nullable|integer|min:0',
        ]);

        $questions = [];
        if (($data['prompt'] ?? '') !== '') {
            $questions[] = $this->questionPayload($data);
        }

        $quiz = $this->host->createQuiz($request->user(), $offering, $data['title'], $questions);

        return redirect()
            ->route('teach.live-quiz.index', $offering)
            ->with('status', __('staff.live_quiz.created', ['title' => $quiz->title]));
    }

    public function addQuestion(Request $request, CourseOffering $offering, LiveQuiz $quiz): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingQuiz($offering, $quiz);

        $data = $request->validate([
            'prompt' => 'required|string|max:2000',
            'time_limit_seconds' => 'nullable|integer|min:1|max:600',
            'points' => 'nullable|integer|min:1|max:100000',
            'options' => 'required|array|min:2',
            'options.*' => 'nullable|string|max:500',
            'correct_index' => 'required|integer|min:0',
        ]);

        $this->host->addQuestion($request->user(), $quiz, $this->questionPayload($data));

        return back()->with('status', __('staff.live_quiz.question_added'));
    }

    public function start(Request $request, CourseOffering $offering, LiveQuiz $quiz): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingQuiz($offering, $quiz);

        try {
            $session = $this->host->startSession($request->user(), $quiz);
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('teach.live-quiz.session', [$offering, $session])
            ->with('status', __('staff.live_quiz.session_started'));
    }

    public function session(Request $request, CourseOffering $offering, LiveQuizSession $session): View
    {
        $this->guardTeach($request, $offering);
        $session->load(['quiz.questions.options', 'currentQuestion.options', 'participants']);
        $this->assertOfferingQuiz($offering, $session->quiz);
        $this->authorize->authorize($request->user(), 'live_quiz.host', $session);

        return view('teach.live-quiz.session', [
            'offering' => $offering->load('course'),
            'session' => $session,
            'quiz' => $session->quiz,
            'autoRefresh' => $session->state !== LiveQuizSessionState::Ended,
        ]);
    }

    public function launch(Request $request, CourseOffering $offering, LiveQuizSession $session): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertSessionOffering($offering, $session);

        $data = $request->validate([
            'question_id' => 'required|string',
        ]);

        $question = LiveQuizQuestion::query()->findOrFail($data['question_id']);

        try {
            $this->host->launchQuestion($request->user(), $session, $question);
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('staff.live_quiz.launched'));
    }

    public function closeQuestion(Request $request, CourseOffering $offering, LiveQuizSession $session): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertSessionOffering($offering, $session);

        try {
            $this->host->closeQuestion($request->user(), $session);
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('staff.live_quiz.closed'));
    }

    public function results(Request $request, CourseOffering $offering, LiveQuizSession $session): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertSessionOffering($offering, $session);

        try {
            $this->host->showResults($request->user(), $session);
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('staff.live_quiz.results_shown'));
    }

    public function end(Request $request, CourseOffering $offering, LiveQuizSession $session): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertSessionOffering($offering, $session);

        try {
            $this->host->endSession($request->user(), $session);
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('teach.live-quiz.index', $offering)
            ->with('status', __('staff.live_quiz.ended'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{prompt: string, time_limit_seconds?: int, points?: int, options: array<int, array{label: string, is_correct: bool}>}
     */
    private function questionPayload(array $data): array
    {
        $labels = array_values(array_filter(array_map('trim', $data['options'] ?? []), fn (string $label) => $label !== ''));
        $correct = (int) ($data['correct_index'] ?? 0);
        $options = [];
        foreach ($labels as $index => $label) {
            $options[] = [
                'label' => $label,
                'is_correct' => $index === $correct,
            ];
        }

        return [
            'prompt' => $data['prompt'],
            'time_limit_seconds' => $data['time_limit_seconds'] ?? 30,
            'points' => $data['points'] ?? 1000,
            'options' => $options,
        ];
    }

    private function assertOfferingQuiz(CourseOffering $offering, ?LiveQuiz $quiz): void
    {
        abort_unless($quiz !== null && $quiz->offering_id === $offering->id, 404);
    }

    private function assertSessionOffering(CourseOffering $offering, LiveQuizSession $session): void
    {
        $session->loadMissing('quiz');
        $this->assertOfferingQuiz($offering, $session->quiz);
    }
}
