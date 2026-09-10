<?php

namespace App\Http\Controllers;

use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectStatus;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Models\CourseOffering;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectMembership;
use App\Models\ProjectPeerEvaluation;
use App\Models\ProjectSubmissionFile;
use App\Services\Projects\PeerEvaluationService;
use App\Services\Projects\ProjectDeliverableService;
use App\Services\Projects\ProjectGradingService;
use App\Services\Projects\ProjectTeamService;
use App\Services\Storage\ObjectStorageService;
use App\Support\Api\StudentPayload;
use App\Support\Api\StudentRecordGuard;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProjectController extends Controller
{
    public function __construct(
        private readonly StudentRecordGuard $guard,
        private readonly AuthorizeService $authorize,
        private readonly ProjectTeamService $teams,
        private readonly ProjectDeliverableService $deliverables,
        private readonly ProjectGradingService $grading,
        private readonly PeerEvaluationService $peers,
        private readonly ObjectStorageService $storage,
    ) {}

    public function mine(Request $request): View
    {
        $this->authorize->authorize($request->user(), 'projects.view');

        $memberships = $this->teamsForStudent($request);

        return view('projects.mine', [
            'memberships' => $memberships,
        ]);
    }

    public function index(Request $request, CourseOffering $offering): View
    {
        $this->guard->enrollmentForRead($request->user(), $offering);
        $this->authorize->authorize($request->user(), 'projects.view');

        $assessments = ProjectAssessment::query()
            ->where('offering_id', $offering->id)
            ->where('status', ProjectAssessmentStatus::Published)
            ->with(['teams' => fn ($q) => $q->where('status', ProjectStatus::Open)->orderBy('name')])
            ->orderBy('title')
            ->get();

        $rows = $assessments->map(function (ProjectAssessment $assessment) use ($request) {
            $membership = $this->teams->activeMembershipForAssessment($request->user(), $assessment);
            $openTeams = $assessment->teams->map(fn (Project $team) => [
                'project' => $team,
                'seats' => $this->teams->activeSeatCount($team),
                'capacity' => $assessment->team_size_max,
            ]);

            return [
                'assessment' => $assessment,
                'membership' => $membership,
                'openTeams' => $openTeams,
                'canLeave' => $membership !== null
                    && $assessment->allow_leave_once
                    && ! $this->teams->hasLeftOnce($request->user(), $assessment),
            ];
        });

        return view('projects.index', [
            'offering' => $offering->loadMissing('course'),
            'rows' => $rows,
        ]);
    }

    public function join(Request $request, CourseOffering $offering, ProjectAssessment $assessment): RedirectResponse
    {
        $this->assertOfferingAssessment($offering, $assessment);
        $this->assertPublishedAssessment($assessment);
        $this->guard->enrollmentForWrite($request->user(), $offering);

        $data = $request->validate([
            'project_id' => 'nullable|string',
        ]);

        try {
            $membership = $this->teams->join(
                $request->user(),
                $assessment,
                $data['project_id'] ?? null,
            );
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('student.projects.show', $membership->project_id)
            ->with('status', __('projects.joined'));
    }

    public function leave(Request $request, CourseOffering $offering, ProjectAssessment $assessment): RedirectResponse
    {
        $this->assertOfferingAssessment($offering, $assessment);
        $this->assertPublishedAssessment($assessment);
        $this->guard->enrollmentForWrite($request->user(), $offering);

        try {
            $this->teams->leave($request->user(), $assessment);
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('student.projects.index', $offering)
            ->with('status', __('projects.left'));
    }

    public function show(Request $request, Project $project): View
    {
        $project->loadMissing(['assessment.offering.course', 'activeMemberships.student']);
        $this->assertPublishedProject($project);
        $this->guard->enrollmentForRead($request->user(), $project->assessment->offering);
        $this->authorize->authorize($request->user(), 'projects.view');

        if ($this->teams->activeMembership($request->user(), $project) === null) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        $assessment = $project->assessment;
        $phases = $assessment->phases()->with('deliverables')->orderBy('position')->get();
        $submissions = $project->submissions()->with(['files', 'deliverable'])->get()->keyBy('deliverable_id');
        $pendingPeers = [];
        if ($assessment->isPeerWindowOpen()) {
            $pendingPeers = $this->peers->pending($request->user(), $project);
        }

        $submittedPeerEvals = ProjectPeerEvaluation::query()
            ->where('project_id', $project->id)
            ->where('rater_id', $request->user()->id)
            ->with('ratee')
            ->get();

        return view('projects.show', [
            'offering' => $assessment->offering,
            'assessment' => $assessment,
            'project' => $project,
            'phases' => $phases,
            'submissions' => $submissions,
            'pendingPeers' => $pendingPeers,
            'submittedPeerEvals' => $submittedPeerEvals,
            'peerWindowOpen' => $assessment->isPeerWindowOpen(),
            'grade' => $this->grading->announcedPercentForStudent($assessment, $request->user()),
            'fileUrl' => fn (?string $path) => StudentPayload::signedFileUrl($path, $this->storage),
        ]);
    }

    public function submit(Request $request, Project $project, ProjectDeliverable $deliverable): RedirectResponse
    {
        $this->assertPublishedProject($project);
        $this->guard->enrollmentForWrite($request->user(), $project->assessment->offering);
        $this->assertWriteMember($request, $project);
        $this->assertDeliverableBelongs($project, $deliverable);

        $maxKb = $deliverable->max_file_mb * 1024;
        $request->validate([
            'body' => 'nullable|string',
            'link' => 'nullable|url',
            'files' => 'nullable|array',
            'files.*' => "file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,gif,zip,txt,odt,ods,odp|max:{$maxKb}",
            'file' => "nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,gif,zip,txt,odt,ods,odp|max:{$maxKb}",
        ]);

        $files = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $upload) {
                if ($upload instanceof UploadedFile) {
                    $files[] = $upload;
                }
            }
        } elseif ($request->hasFile('file')) {
            $upload = $request->file('file');
            if ($upload instanceof UploadedFile) {
                $files[] = $upload;
            }
        }

        try {
            $this->deliverables->submit(
                $request->user(),
                $project,
                $deliverable,
                [
                    'body' => $request->input('body'),
                    'link' => $request->input('link'),
                ],
                $files,
            );
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('student.projects.show', $project)
            ->with('status', __('projects.submitted'));
    }

    public function destroyFile(Request $request, Project $project, ProjectSubmissionFile $file): RedirectResponse
    {
        $this->assertPublishedProject($project);
        $this->guard->enrollmentForWrite($request->user(), $project->assessment->offering);
        $this->assertWriteMember($request, $project);

        $file->loadMissing('submission');
        if ($file->submission?->project_id !== $project->id) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        try {
            $this->deliverables->deleteFile($request->user(), $project, $file);
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('student.projects.show', $project)
            ->with('status', __('projects.file_deleted'));
    }

    public function storePeerEvaluation(Request $request, Project $project): RedirectResponse
    {
        $this->assertPublishedProject($project);
        $this->guard->enrollmentForWrite($request->user(), $project->assessment->offering);
        $this->assertWriteMember($request, $project);

        $data = $request->validate([
            'ratee_id' => 'required|string',
            'score' => 'required|numeric|min:0|max:100',
            'comment' => 'nullable|string',
        ]);

        try {
            $this->peers->submit($request->user(), $project, $data);
        } catch (ConflictException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('student.projects.show', $project)
            ->with('status', __('projects.peer_saved'));
    }

    private function assertOfferingAssessment(CourseOffering $offering, ProjectAssessment $assessment): void
    {
        abort_unless($assessment->offering_id === $offering->id, 404);
    }

    private function assertPublishedAssessment(ProjectAssessment $assessment): void
    {
        $assessment->loadMissing('offering');
        if ($assessment->status !== ProjectAssessmentStatus::Published) {
            throw new NotFoundHttpException;
        }
    }

    private function assertPublishedProject(Project $project): void
    {
        $project->loadMissing('assessment.offering');
        if ($project->assessment === null || $project->assessment->status !== ProjectAssessmentStatus::Published) {
            throw new NotFoundHttpException;
        }
    }

    private function assertWriteMember(Request $request, Project $project): void
    {
        if ($this->teams->activeMembership($request->user(), $project) === null) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }

    private function assertDeliverableBelongs(Project $project, ProjectDeliverable $deliverable): void
    {
        $deliverable->loadMissing('phase');
        if ($deliverable->phase?->project_assessment_id !== $project->project_assessment_id) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\ProjectMembership>
     */
    private function teamsForStudent(Request $request)
    {
        return ProjectMembership::query()
            ->where('student_id', $request->user()->id)
            ->whereNull('left_at')
            ->whereHas('project.assessment', fn ($q) => $q->where('status', ProjectAssessmentStatus::Published))
            ->with(['project.assessment.offering.course'])
            ->orderByDesc('joined_at')
            ->get();
    }
}
