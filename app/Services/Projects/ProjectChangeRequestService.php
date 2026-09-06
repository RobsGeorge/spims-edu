<?php

namespace App\Services\Projects;

use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectChangeRequestKind;
use App\Enums\ProjectChangeRequestStatus;
use App\Enums\ProjectMembershipEventKind;
use App\Enums\ProjectMembershipRole;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectChangeRequest;
use App\Models\ProjectMembership;
use App\Models\ProjectMembershipEvent;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;

class ProjectChangeRequestService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly ProjectTeamService $teams,
    ) {}

    /**
     * @param  array{kind: string, reason: string, target_project_id?: ?string, meta?: ?array}  $data
     */
    public function raise(User $student, ProjectAssessment $assessment, array $data): ProjectChangeRequest
    {
        $this->authorize->authorize($student, 'projects.join');
        $this->teams->assertEnrolled($student, $assessment);

        if ($assessment->status !== ProjectAssessmentStatus::Published) {
            throw new ConflictException(__('projects.not_published'));
        }

        return $this->audit->withAudit($student, 'projects.change_request_raise', function () use ($student, $assessment, $data) {
            return ProjectChangeRequest::query()->create([
                'project_assessment_id' => $assessment->id,
                'student_id' => $student->id,
                'kind' => $data['kind'],
                'reason' => $data['reason'],
                'status' => ProjectChangeRequestStatus::Pending,
                'target_project_id' => $data['target_project_id'] ?? null,
                'meta' => $data['meta'] ?? null,
            ]);
        }, 'ProjectChangeRequest');
    }

    public function approve(User $actor, ProjectChangeRequest $request, ?string $note = null): ProjectChangeRequest
    {
        $request->loadMissing('assessment');
        $this->authorize->authorize($actor, 'projects.manage', $request->assessment);

        if ($request->status !== ProjectChangeRequestStatus::Pending) {
            throw new ConflictException(__('projects.change_not_pending'));
        }

        return $this->audit->withAudit($actor, 'projects.change_request_approve', function () use ($actor, $request, $note) {
            return DB::transaction(function () use ($actor, $request, $note) {
                $locked = ProjectChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== ProjectChangeRequestStatus::Pending) {
                    throw new ConflictException(__('projects.change_not_pending'));
                }

                $this->apply($locked);

                $locked->status = ProjectChangeRequestStatus::Approved;
                $locked->decided_by_id = $actor->id;
                $locked->decided_at = now();
                $locked->decision_note = $note;
                $locked->save();

                return $locked->fresh();
            });
        }, 'ProjectChangeRequest');
    }

    public function reject(User $actor, ProjectChangeRequest $request, ?string $note = null): ProjectChangeRequest
    {
        $request->loadMissing('assessment');
        $this->authorize->authorize($actor, 'projects.manage', $request->assessment);

        if ($request->status !== ProjectChangeRequestStatus::Pending) {
            throw new ConflictException(__('projects.change_not_pending'));
        }

        return $this->audit->withAudit($actor, 'projects.change_request_reject', function () use ($actor, $request, $note) {
            $request->status = ProjectChangeRequestStatus::Rejected;
            $request->decided_by_id = $actor->id;
            $request->decided_at = now();
            $request->decision_note = $note;
            $request->save();

            return $request->fresh();
        }, 'ProjectChangeRequest');
    }

    private function apply(ProjectChangeRequest $request): void
    {
        $student = $request->student()->firstOrFail();
        $assessment = $request->assessment()->firstOrFail();
        $current = $this->teams->activeMembershipForAssessment($student, $assessment);

        match ($request->kind) {
            ProjectChangeRequestKind::Move => $this->applyMove($student, $current, $request),
            ProjectChangeRequestKind::Merge => $this->applyMerge($current, $request),
            ProjectChangeRequestKind::Leave => $this->applyLeave($current),
            ProjectChangeRequestKind::Join => $this->applyJoin($student, $assessment, $request),
        };
    }

    private function applyMove(User $student, ?ProjectMembership $current, ProjectChangeRequest $request): void
    {
        if ($current === null || $request->target_project_id === null) {
            throw new ConflictException(__('projects.not_member'));
        }

        $target = Project::query()->findOrFail($request->target_project_id);
        $this->teams->applyMove($student, $current->project, $target);
    }

    private function applyMerge(?ProjectMembership $current, ProjectChangeRequest $request): void
    {
        if ($current === null || $request->target_project_id === null) {
            throw new ConflictException(__('projects.not_member'));
        }

        $target = Project::query()->findOrFail($request->target_project_id);
        $this->teams->applyMerge($current->project, $target);
    }

    private function applyLeave(?ProjectMembership $current): void
    {
        if ($current === null) {
            throw new ConflictException(__('projects.not_member'));
        }

        $current->left_at = now();
        $current->save();

        ProjectMembershipEvent::query()->create([
            'project_id' => $current->project_id,
            'student_id' => $current->student_id,
            'kind' => ProjectMembershipEventKind::Leave,
            'meta' => ['via' => 'change_request'],
        ]);
    }

    private function applyJoin(User $student, ProjectAssessment $assessment, ProjectChangeRequest $request): void
    {
        if ($this->teams->activeMembershipForAssessment($student, $assessment) !== null) {
            throw new ConflictException(__('projects.already_member'));
        }

        $projectId = $request->target_project_id;
        if ($projectId === null) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        $project = Project::query()
            ->whereKey($projectId)
            ->where('project_assessment_id', $assessment->id)
            ->firstOrFail();

        if ($this->teams->activeSeatCount($project) >= $assessment->team_size_max) {
            throw new ConflictException(__('projects.at_capacity'));
        }

        ProjectMembership::query()->create([
            'project_id' => $project->id,
            'student_id' => $student->id,
            'role' => ProjectMembershipRole::Member,
            'joined_at' => now(),
        ]);

        ProjectMembershipEvent::query()->create([
            'project_id' => $project->id,
            'student_id' => $student->id,
            'kind' => ProjectMembershipEventKind::Join,
            'meta' => ['via' => 'change_request'],
        ]);
    }
}
