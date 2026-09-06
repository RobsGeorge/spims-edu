<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectAssessmentStatus;
use App\Exceptions\AuthorizationException;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectSubmissionFile;
use App\Services\Projects\PeerEvaluationService;
use App\Services\Projects\ProjectDeliverableService;
use App\Services\Projects\ProjectGradingService;
use App\Services\Projects\ProjectTeamService;
use App\Services\Storage\ObjectStorageService;
use App\Support\Api\IdempotencyStore;
use App\Support\Api\StudentPayload;
use App\Support\Api\StudentRecordGuard;
use App\Support\AuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function index(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->guard->enrollmentForRead($request->user(), $offering);
        $this->authorize->authorize($request->user(), 'projects.view');

        $assessments = ProjectAssessment::query()
            ->where('offering_id', $offering->id)
            ->where('status', ProjectAssessmentStatus::Published)
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $assessments->map(fn (ProjectAssessment $assessment) => $this->assessmentPayload($request, $assessment))->values()->all(),
        ]);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $project->loadMissing(['assessment.offering', 'activeMemberships.student']);
        $assessment = $project->assessment;
        if ($assessment === null || $assessment->status !== ProjectAssessmentStatus::Published) {
            throw new NotFoundHttpException;
        }

        $this->guard->enrollmentForRead($request->user(), $assessment->offering);
        $this->authorize->authorize($request->user(), 'projects.view');

        if ($this->teams->activeMembership($request->user(), $project) === null) {
            throw new NotFoundHttpException;
        }

        return response()->json(['data' => $this->projectPayload($request, $project)]);
    }

    public function join(Request $request, ProjectAssessment $projectAssessment): JsonResponse
    {
        $this->assertPublishedAssessment($projectAssessment);
        $this->guard->enrollmentForWrite($request->user(), $projectAssessment->offering);

        $data = $request->validate([
            'project_id' => 'nullable|string',
        ]);

        $payload = $this->idempotency->remember(
            $request->user(),
            'projects.join:'.$projectAssessment->id,
            $request->header('Idempotency-Key'),
            function () use ($request, $projectAssessment, $data) {
                $membership = $this->teams->join(
                    $request->user(),
                    $projectAssessment,
                    $data['project_id'] ?? null,
                );

                return $this->membershipPayload($membership->fresh('project'));
            },
        );

        return response()->json(['data' => $payload], 201);
    }

    public function leave(Request $request, ProjectAssessment $projectAssessment): JsonResponse
    {
        $this->assertPublishedAssessment($projectAssessment);
        $this->guard->enrollmentForWrite($request->user(), $projectAssessment->offering);

        $payload = $this->idempotency->remember(
            $request->user(),
            'projects.leave:'.$projectAssessment->id,
            $request->header('Idempotency-Key'),
            function () use ($request, $projectAssessment) {
                $membership = $this->teams->leave($request->user(), $projectAssessment);

                return [
                    'id' => $membership->id,
                    'project_id' => $membership->project_id,
                    'left_at' => StudentPayload::iso($membership->left_at),
                ];
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function submit(Request $request, Project $project, ProjectDeliverable $projectDeliverable): JsonResponse
    {
        $this->assertPublishedProject($project);
        $this->guard->enrollmentForWrite($request->user(), $project->assessment->offering);
        $this->assertWriteMember($request, $project);
        $this->assertDeliverableBelongs($project, $projectDeliverable);

        $request->validate([
            'body' => 'nullable|string',
            'link' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'file',
            'file' => 'nullable|file',
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

        $payload = $this->idempotency->remember(
            $request->user(),
            'projects.submit:'.$project->id.':'.$projectDeliverable->id,
            $request->header('Idempotency-Key'),
            function () use ($request, $project, $projectDeliverable, $files) {
                $submission = $this->deliverables->submit(
                    $request->user(),
                    $project,
                    $projectDeliverable,
                    [
                        'body' => $request->input('body'),
                        'link' => $request->input('link'),
                    ],
                    $files,
                );

                return $this->submissionPayload($submission);
            },
        );

        return response()->json(['data' => $payload], 201);
    }

    public function destroyFile(Request $request, Project $project, ProjectSubmissionFile $projectSubmissionFile): JsonResponse
    {
        $this->assertPublishedProject($project);
        $this->guard->enrollmentForWrite($request->user(), $project->assessment->offering);
        $this->assertWriteMember($request, $project);

        $projectSubmissionFile->loadMissing('submission');
        if ($projectSubmissionFile->submission?->project_id !== $project->id) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        $this->deliverables->deleteFile($request->user(), $project, $projectSubmissionFile);

        return response()->json(['data' => ['id' => $projectSubmissionFile->id, 'deleted' => true]]);
    }

    public function pendingPeerEvaluations(Request $request, Project $project): JsonResponse
    {
        $this->assertPublishedProject($project);
        $this->guard->enrollmentForRead($request->user(), $project->assessment->offering);

        if ($this->teams->activeMembership($request->user(), $project) === null) {
            throw new NotFoundHttpException;
        }

        return response()->json(['data' => $this->peers->pending($request->user(), $project)]);
    }

    public function storePeerEvaluation(Request $request, Project $project): JsonResponse
    {
        $this->assertPublishedProject($project);
        $this->guard->enrollmentForWrite($request->user(), $project->assessment->offering);
        $this->assertWriteMember($request, $project);

        $data = $request->validate([
            'ratee_id' => 'required|string',
            'score' => 'required|numeric|min:0|max:100',
            'comment' => 'nullable|string',
        ]);

        $eval = $this->peers->submit($request->user(), $project, $data);

        return response()->json([
            'data' => [
                'id' => $eval->id,
                'ratee_id' => $eval->ratee_id,
                'score' => $eval->score,
                'submitted_at' => StudentPayload::iso($eval->submitted_at),
            ],
        ], 201);
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

    /** @return array<string, mixed> */
    private function assessmentPayload(Request $request, ProjectAssessment $assessment): array
    {
        $membership = $this->teams->activeMembershipForAssessment($request->user(), $assessment);

        return [
            'id' => $assessment->id,
            'title' => $assessment->title,
            'status' => $assessment->status->value,
            'team_size_min' => $assessment->team_size_min,
            'team_size_max' => $assessment->team_size_max,
            'join_opens_at' => StudentPayload::iso($assessment->join_opens_at),
            'join_closes_at' => StudentPayload::iso($assessment->join_closes_at),
            'grading_mode' => $assessment->grading_mode->value,
            'max_points' => $assessment->max_points,
            'project' => $membership === null ? null : [
                'id' => $membership->project_id,
                'role' => $membership->role->value,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function projectPayload(Request $request, Project $project): array
    {
        $assessment = $project->assessment;
        $phases = $assessment->phases()->with('deliverables')->orderBy('position')->get();
        $submissions = $project->submissions()->with(['files', 'deliverable'])->get()->keyBy('deliverable_id');
        $grade = $this->grading->announcedPercentForStudent($assessment, $request->user());

        return [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status->value,
            'workspace_url' => $project->workspace_url,
            'assessment_id' => $assessment->id,
            'members' => $project->activeMemberships->map(fn ($m) => [
                'id' => $m->student_id,
                'first_name' => $m->student?->first_name,
                'last_name' => $m->student?->last_name,
                'role' => $m->role->value,
            ])->values()->all(),
            'phases' => $phases->map(function ($phase) use ($submissions) {
                return [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'position' => $phase->position,
                    'due_at' => StudentPayload::iso($phase->due_at),
                    'deliverables' => $phase->deliverables->map(function ($d) use ($submissions) {
                        $sub = $submissions->get($d->id);

                        return [
                            'id' => $d->id,
                            'kind' => $d->kind->value,
                            'title' => $d->title,
                            'due_at' => StudentPayload::iso($d->due_at),
                            'points' => $d->points,
                            'max_files' => $d->max_files,
                            'submitted' => $sub !== null,
                            'late' => $sub?->late ?? false,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
            'grade' => $grade,
        ];
    }

    /** @return array<string, mixed> */
    private function membershipPayload($membership): array
    {
        return [
            'id' => $membership->id,
            'project_id' => $membership->project_id,
            'project_name' => $membership->project?->name,
            'role' => $membership->role->value,
            'joined_at' => StudentPayload::iso($membership->joined_at),
        ];
    }

    /** @return array<string, mixed> */
    private function submissionPayload($submission): array
    {
        return [
            'id' => $submission->id,
            'project_id' => $submission->project_id,
            'deliverable_id' => $submission->deliverable_id,
            'body' => $submission->body,
            'link' => $submission->link,
            'late' => $submission->late,
            'files' => $submission->files->map(fn ($f) => [
                'id' => $f->id,
                'original_name' => $f->original_name,
                'size_bytes' => $f->size_bytes,
                'url' => StudentPayload::signedFileUrl($f->path, $this->storage),
            ])->values()->all(),
        ];
    }
}
