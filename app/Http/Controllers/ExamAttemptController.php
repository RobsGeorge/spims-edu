<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Services\Assessment\AttemptService;
use App\Services\Assessment\ResultsVisibilityService;
use App\Services\Learning\OfferingAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamAttemptController extends Controller
{
    public function show(
        Assessment $assessment,
        OfferingAccessService $access,
        ResultsVisibilityService $visibility,
    ): View {
        $user = auth()->user();
        $access->assertCanAccessAssessment($user, $assessment);

        $showScores = $visibility->scoresVisible($assessment, $user);
        $showAnswers = $visibility->answersVisible($assessment, $user);

        $attempts = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $user->id)
            ->latest('attempt_no')
            ->get();

        if ($showAnswers) {
            $attempts->load(['answers.question.options']);
        }

        return view('assessments.show', [
            'assessment' => $assessment->load('offering.course', 'resultAnnouncement'),
            'attempts' => $attempts,
            'showScores' => $showScores,
            'showAnswers' => $showAnswers,
        ]);
    }

    public function start(Request $request, Assessment $assessment, AttemptService $attempts): RedirectResponse
    {
        $attempt = $attempts->start($request->user(), $assessment);

        return redirect()->route('assessments.runner', $attempt);
    }

    public function runner(AssessmentAttempt $attempt): View
    {
        abort_unless($attempt->student_id === auth()->id() || auth()->user()->isSuperAdmin(), 403);

        return view('assessments.runner', [
            'attempt' => $attempt->load('assessment', 'answers'),
            'serverNow' => now()->toIso8601String(),
            'dueAt' => $attempt->due_at->toIso8601String(),
        ]);
    }

    public function save(Request $request, AssessmentAttempt $attempt, AttemptService $attempts): JsonResponse
    {
        $data = $request->validate([
            'answers' => 'required|array',
        ]);

        $saved = $attempts->autosave($request->user(), $attempt, $data['answers']);

        return response()->json([
            'status' => $saved->status->value,
            'server_now' => now()->toIso8601String(),
            'due_at' => $saved->due_at->toIso8601String(),
            'remaining_seconds' => max(0, now()->diffInSeconds($saved->due_at, false)),
        ]);
    }

    public function submit(Request $request, AssessmentAttempt $attempt, AttemptService $attempts): RedirectResponse
    {
        if ($request->filled('answers') && is_array($request->input('answers'))) {
            $attempts->autosave($request->user(), $attempt, $request->input('answers'));
        }

        $attempts->submit($request->user(), $attempt->fresh());

        return redirect()->route('assessments.show', $attempt->assessment_id)
            ->with('status', __('assessment.submitted'));
    }

    public function upload(Request $request, AssessmentAttempt $attempt, AttemptService $attempts): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $stored = $attempts->uploadFile($request->user(), $attempt, $data['file']);

        return response()->json($stored, 201);
    }

    public function focusLoss(Request $request, AssessmentAttempt $attempt, AttemptService $attempts): JsonResponse
    {
        $updated = $attempts->logFocusLoss($request->user(), $attempt);

        return response()->json([
            'focus_loss_count' => $updated->focus_loss_count,
            'proctor_warnings' => $updated->proctor_warnings,
            'terminated_for_cheating' => $updated->terminated_for_cheating,
            'status' => $updated->status->value,
        ]);
    }

    public function timer(AssessmentAttempt $attempt): JsonResponse
    {
        abort_unless($attempt->student_id === auth()->id() || auth()->user()->isSuperAdmin(), 403);

        return response()->json([
            'server_now' => now()->toIso8601String(),
            'due_at' => $attempt->due_at->toIso8601String(),
            'remaining_seconds' => max(0, now()->diffInSeconds($attempt->due_at, false)),
            'expired' => $attempt->isExpired() || $attempt->status->value !== 'IN_PROGRESS',
            'status' => $attempt->status->value,
        ]);
    }
}
