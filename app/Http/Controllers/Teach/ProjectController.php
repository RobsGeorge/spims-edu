<?php

namespace App\Http\Controllers\Teach;

use App\Enums\ProjectGradingMode;
use App\Enums\ProjectReviewStatus;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teach\Concerns\GuardsTeachOffering;
use App\Models\CourseOffering;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectGrade;
use App\Models\User;
use App\Services\Projects\PeerEvaluationService;
use App\Services\Projects\ProjectAssessmentService;
use App\Services\Projects\ProjectDeliverableService;
use App\Services\Projects\ProjectGradingService;
use App\Services\Projects\ProjectTeamService;
use App\Support\AuthorizeService;
use App\Support\ConfirmationToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    use GuardsTeachOffering;

    public function __construct(
        private readonly ProjectAssessmentService $assessments,
        private readonly ProjectTeamService $teams,
        private readonly ProjectGradingService $grading,
        private readonly ProjectDeliverableService $deliverables,
        private readonly PeerEvaluationService $peers,
        private readonly AuthorizeService $authorize,
        private readonly ConfirmationToken $confirm,
    ) {}

    public function index(Request $request, CourseOffering $offering): View
    {
        $this->guardTeach($request, $offering);
        $this->authorize->authorize($request->user(), 'projects.view', $offering);

        $items = ProjectAssessment::query()
            ->where('offering_id', $offering->id)
            ->orderBy('title')
            ->get();

        return view('teach.projects.index', [
            'offering' => $offering->load('course'),
            'assessments' => $items,
            'gradingModes' => ProjectGradingMode::cases(),
        ]);
    }

    public function store(Request $request, CourseOffering $offering): RedirectResponse
    {
        $this->guardTeach($request, $offering);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'team_size_min' => 'nullable|integer|min:1|max:20',
            'team_size_max' => 'nullable|integer|min:1|max:20',
            'grading_mode' => 'nullable|in:RUBRIC,DELIVERABLES',
            'max_points' => 'nullable|numeric|min:1|max:10000',
        ]);

        $assessment = $this->assessments->create($request->user(), $offering, [
            'title' => $data['title'],
            'team_size_min' => $data['team_size_min'] ?? 1,
            'team_size_max' => $data['team_size_max'] ?? 4,
            'grading_mode' => $data['grading_mode'] ?? ProjectGradingMode::Rubric->value,
            'max_points' => $data['max_points'] ?? 100,
            'join_opens_at' => now(),
            'join_closes_at' => now()->addMonths(6),
        ]);

        if (($data['grading_mode'] ?? ProjectGradingMode::Rubric->value) === ProjectGradingMode::Rubric->value) {
            $this->grading->setCriteria($request->user(), $assessment, [
                ['name' => __('staff.projects.default_criterion'), 'weight' => 100, 'level' => 'TEAM'],
            ]);
        }

        return redirect()
            ->route('teach.projects.show', [$offering, $assessment])
            ->with('status', __('staff.projects.created'));
    }

    public function show(Request $request, CourseOffering $offering, ProjectAssessment $assessment): View
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);
        $this->authorize->authorize($request->user(), 'projects.view', $assessment);

        $assessment->load([
            'teams.activeMemberships.student',
            'phases.deliverables',
            'criteria',
        ]);

        $seating = $this->teams->seating($request->user(), $assessment);
        foreach ($seating as &$row) {
            $row['project']->loadMissing('activeMemberships.student');
        }
        unset($row);

        $submissions = ProjectDeliverableSubmission::query()
            ->whereIn('project_id', $assessment->teams->pluck('id'))
            ->with(['deliverable', 'project'])
            ->orderByDesc('updated_at')
            ->get();

        $pendingGrades = ProjectGrade::query()
            ->where('project_assessment_id', $assessment->id)
            ->whereNull('announced_at')
            ->count();

        $confirmToken = null;
        try {
            $this->authorize->authorize($request->user(), 'projects.announce', $assessment);
            $confirmToken = $this->confirm->issue('projects.announce.'.$assessment->id);
        } catch (AuthorizationException) {
            $confirmToken = null;
        }

        return view('teach.projects.show', [
            'offering' => $offering->load('course'),
            'assessment' => $assessment,
            'seating' => $seating,
            'submissions' => $submissions,
            'peerAggregates' => $this->peers->aggregatesForStaff($request->user(), $assessment),
            'pendingGrades' => $pendingGrades,
            'confirmToken' => $confirmToken,
        ]);
    }

    public function publish(Request $request, CourseOffering $offering, ProjectAssessment $assessment): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);

        $this->assessments->publish($request->user(), $assessment);

        return back()->with('status', __('staff.projects.published'));
    }

    public function move(Request $request, CourseOffering $offering, ProjectAssessment $assessment): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);

        $data = $request->validate([
            'student_id' => 'required|string|exists:users,id',
            'from_project_id' => 'required|string|exists:projects,id',
            'to_project_id' => 'required|string|exists:projects,id',
        ]);

        $from = $this->projectOnAssessment($assessment, $data['from_project_id']);
        $to = $this->projectOnAssessment($assessment, $data['to_project_id']);

        try {
            $this->teams->move(
                $request->user(),
                User::query()->findOrFail($data['student_id']),
                $from,
                $to,
            );
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('staff.projects.moved'));
    }

    public function merge(Request $request, CourseOffering $offering, ProjectAssessment $assessment): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);

        $data = $request->validate([
            'source_project_id' => 'required|string|exists:projects,id',
            'target_project_id' => 'required|string|exists:projects,id',
        ]);

        $source = $this->projectOnAssessment($assessment, $data['source_project_id']);
        $target = $this->projectOnAssessment($assessment, $data['target_project_id']);

        try {
            $this->teams->merge($request->user(), $source, $target);
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('staff.projects.merged'));
    }

    public function teamScore(Request $request, CourseOffering $offering, ProjectAssessment $assessment): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);

        $data = $request->validate([
            'project_id' => 'required|string|exists:projects,id',
            'score' => 'required|numeric|min:0|max:10000',
        ]);

        $project = $this->projectOnAssessment($assessment, $data['project_id']);
        $this->grading->setTeamScore($request->user(), $project, (float) $data['score']);

        return back()->with('status', __('staff.projects.score_saved'));
    }

    public function studentScore(Request $request, CourseOffering $offering, ProjectAssessment $assessment): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);

        $data = $request->validate([
            'student_id' => 'required|string|exists:users,id',
            'score' => 'required|numeric|min:0|max:10000',
        ]);

        $this->grading->setStudentOverride(
            $request->user(),
            $assessment,
            User::query()->findOrFail($data['student_id']),
            (float) $data['score'],
        );

        return back()->with('status', __('staff.projects.score_saved'));
    }

    public function announce(Request $request, CourseOffering $offering, ProjectAssessment $assessment): RedirectResponse
    {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);
        $this->authorize->authorize($request->user(), 'projects.announce', $assessment);

        $this->confirm->consume(
            'projects.announce.'.$assessment->id,
            $request->input('confirmation_token'),
        );

        $count = $this->grading->announce($request->user(), $assessment);

        return back()->with('status', __('staff.projects.announced', ['count' => $count]));
    }

    public function showSubmission(
        Request $request,
        CourseOffering $offering,
        ProjectAssessment $assessment,
        ProjectDeliverableSubmission $submission,
    ): View {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);
        $this->assertSubmissionOnAssessment($assessment, $submission);
        $this->authorize->authorize($request->user(), 'projects.grade', $assessment);

        return view('teach.projects.submission', [
            'offering' => $offering->load('course'),
            'assessment' => $assessment,
            'submission' => $submission->load(['deliverable', 'project', 'files']),
            'reviewStatuses' => ProjectReviewStatus::cases(),
        ]);
    }

    public function reviewSubmission(
        Request $request,
        CourseOffering $offering,
        ProjectAssessment $assessment,
        ProjectDeliverableSubmission $submission,
    ): RedirectResponse {
        $this->guardTeach($request, $offering);
        $this->assertOfferingAssessment($offering, $assessment);
        $this->assertSubmissionOnAssessment($assessment, $submission);

        $data = $request->validate([
            'review_status' => 'required|in:PENDING,ACCEPTED,REJECTED,NEEDS_REVISION',
        ]);

        $this->deliverables->review($request->user(), $submission, $data);

        return back()->with('status', __('staff.projects.reviewed'));
    }

    private function assertOfferingAssessment(CourseOffering $offering, ProjectAssessment $assessment): void
    {
        abort_unless($assessment->offering_id === $offering->id, 404);
    }

    private function projectOnAssessment(ProjectAssessment $assessment, string $projectId): Project
    {
        $project = Project::query()->whereKey($projectId)->firstOrFail();
        abort_unless($project->project_assessment_id === $assessment->id, 404);

        return $project;
    }

    private function assertSubmissionOnAssessment(ProjectAssessment $assessment, ProjectDeliverableSubmission $submission): void
    {
        $submission->loadMissing('project');
        abort_unless($submission->project?->project_assessment_id === $assessment->id, 404);
    }
}
