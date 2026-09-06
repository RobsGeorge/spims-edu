<?php

namespace App\Http\Controllers\Teach;

use App\Enums\AttemptStatus;
use App\Exceptions\AuthorizationException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teach\Concerns\GuardsTeachOffering;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentResultAnnouncement;
use App\Models\AttemptAnswer;
use App\Models\CourseOffering;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AttemptService;
use App\Support\AuthorizeService;
use App\Support\ConfirmationToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    use GuardsTeachOffering;

    public function __construct(
        private readonly AttemptService $attempts,
        private readonly AssessmentService $assessments,
        private readonly AuthorizeService $authorize,
        private readonly ConfirmationToken $confirm,
    ) {}

    public function attempts(Request $request, CourseOffering $offering, Assessment $assessment): View
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);
        $this->authorize->authorize($request->user(), 'assessments.grade', $assessment);

        $rows = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->with(['student', 'answers.question', 'proctorEvents'])
            ->orderBy('attempt_no')
            ->get();

        $confirmToken = null;
        try {
            $this->authorize->authorize($request->user(), 'assessments.announce_results', $assessment);
            $confirmToken = $this->confirm->issue('assessments.announce_results.'.$assessment->id);
        } catch (AuthorizationException) {
            $confirmToken = null;
        }

        return view('teach.assessments.attempts', [
            'offering' => $offering->load('course'),
            'assessment' => $assessment,
            'attempts' => $rows,
            'confirmToken' => $confirmToken,
            'announcement' => AssessmentResultAnnouncement::query()
                ->where('assessment_id', $assessment->id)
                ->first(),
            'gradeableStatuses' => [
                AttemptStatus::Submitted,
                AttemptStatus::AutoSubmitted,
                AttemptStatus::Graded,
            ],
        ]);
    }

    public function gradeAnswer(
        Request $request,
        CourseOffering $offering,
        Assessment $assessment,
        AttemptAnswer $attemptAnswer,
    ): RedirectResponse {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);
        $this->assertAnswerOnAssessment($assessment, $attemptAnswer);

        $data = $request->validate([
            'final_score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ]);

        $this->attempts->overrideScore(
            $request->user(),
            $attemptAnswer,
            (float) $data['final_score'],
            $data['feedback'] ?? null,
        );

        return back()->with('status', __('assessment.score_saved'));
    }

    public function announceResults(Request $request, CourseOffering $offering, Assessment $assessment): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);
        $this->authorize->authorize($request->user(), 'assessments.announce_results', $assessment);

        $this->confirm->consume(
            'assessments.announce_results.'.$assessment->id,
            $request->input('confirmation_token'),
        );

        $this->assessments->announceResults($request->user(), $assessment);

        return back()->with('status', __('assessment.results_announced'));
    }

    private function assertOfferingAssessment(CourseOffering $offering, Assessment $assessment): void
    {
        abort_unless($assessment->offering_id === $offering->id, 403);
    }

    private function assertAnswerOnAssessment(Assessment $assessment, AttemptAnswer $attemptAnswer): void
    {
        $attemptAnswer->loadMissing('attempt');
        abort_unless($attemptAnswer->attempt?->assessment_id === $assessment->id, 403);
    }
}
