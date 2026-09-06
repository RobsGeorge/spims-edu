<?php

namespace App\Services\Projects;

use App\Enums\EnrollmentStatus;
use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectMembershipEventKind;
use App\Enums\ProjectMembershipRole;
use App\Enums\ProjectStatus;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Models\Enrollment;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectMembership;
use App\Models\ProjectMembershipEvent;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Validation\ValidationException;

class ProjectTeamService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function join(User $student, ProjectAssessment $assessment, ?string $projectId = null): ProjectMembership
    {
        $this->authorize->authorize($student, 'projects.join');
        $this->assertEnrolled($student, $assessment);
        $this->assertPublished($assessment);
        $this->assertNotLocked($assessment);

        if (! $assessment->isJoinWindowOpen()) {
            throw new ConflictException(__('projects.join_window'));
        }

        return $this->audit->withAudit($student, 'projects.join', function () use ($student, $assessment, $projectId) {
            $locked = ProjectAssessment::query()->whereKey($assessment->id)->lockForUpdate()->firstOrFail();

            if ($this->activeMembershipForAssessment($student, $locked) !== null) {
                throw new ConflictException(__('projects.already_member'));
            }

            $project = $this->seatOnTeam($locked, $projectId);
            $activeCount = $this->activeSeatCount($project);
            if ($activeCount >= $locked->team_size_max) {
                throw new ConflictException(__('projects.at_capacity'));
            }

            $membership = ProjectMembership::query()->create([
                'project_id' => $project->id,
                'student_id' => $student->id,
                'role' => $activeCount === 0 ? ProjectMembershipRole::Leader : ProjectMembershipRole::Member,
                'joined_at' => now(),
            ]);

            $this->recordEvent($project, $student, ProjectMembershipEventKind::Join);

            return $membership->fresh('project');
        }, 'ProjectMembership');
    }

    public function leave(User $student, ProjectAssessment $assessment): ProjectMembership
    {
        $this->authorize->authorize($student, 'projects.join');
        $this->assertEnrolled($student, $assessment);
        $this->assertPublished($assessment);
        $this->assertNotLocked($assessment);

        if (! $assessment->allow_leave_once) {
            throw new ConflictException(__('projects.leave_not_allowed'));
        }

        if ($this->hasLeftOnce($student, $assessment)) {
            throw new ConflictException(__('projects.leave_used'));
        }

        return $this->audit->withAudit($student, 'projects.leave', function () use ($student, $assessment) {
            $membership = $this->activeMembershipForAssessment($student, $assessment);
            if ($membership === null) {
                throw new ConflictException(__('projects.not_member'));
            }

            $membership->left_at = now();
            $membership->save();

            $this->recordEvent($membership->project, $student, ProjectMembershipEventKind::Leave);

            return $membership->fresh();
        }, 'ProjectMembership');
    }

    public function move(User $actor, User $student, Project $from, Project $to): ProjectMembership
    {
        $from->loadMissing('assessment');
        $this->authorize->authorize($actor, 'projects.manage', $from->assessment);
        $this->assertSameAssessment($from, $to);
        $this->assertOpen($to);

        return $this->audit->withAudit($actor, 'projects.move', function () use ($student, $from, $to) {
            return $this->applyMove($student, $from, $to);
        }, 'ProjectMembership');
    }

    public function merge(User $actor, Project $source, Project $target): Project
    {
        $source->loadMissing('assessment');
        $this->authorize->authorize($actor, 'projects.manage', $source->assessment);
        $this->assertSameAssessment($source, $target);
        $this->assertOpen($source);
        $this->assertOpen($target);

        return $this->audit->withAudit($actor, 'projects.merge', function () use ($source, $target) {
            $this->applyMerge($source, $target);

            return $target->fresh();
        }, 'Project');
    }

    /**
     * @return array<int, array{project: Project, seats: int, capacity: int}>
     */
    public function seating(User $actor, ProjectAssessment $assessment): array
    {
        $this->authorize->authorize($actor, 'projects.view', $assessment);

        $rows = [];
        foreach ($assessment->teams()->where('status', ProjectStatus::Open)->orderBy('created_at')->get() as $project) {
            $rows[] = [
                'project' => $project,
                'seats' => $this->activeSeatCount($project),
                'capacity' => $assessment->team_size_max,
            ];
        }

        return $rows;
    }

    public function activeMembership(User $student, Project $project): ?ProjectMembership
    {
        return ProjectMembership::query()
            ->where('project_id', $project->id)
            ->where('student_id', $student->id)
            ->whereNull('left_at')
            ->first();
    }

    public function activeMembershipForAssessment(User $student, ProjectAssessment $assessment): ?ProjectMembership
    {
        return ProjectMembership::query()
            ->where('student_id', $student->id)
            ->whereNull('left_at')
            ->whereHas('project', fn ($q) => $q->where('project_assessment_id', $assessment->id)
                ->where('status', ProjectStatus::Open))
            ->first();
    }

    public function hasLeftOnce(User $student, ProjectAssessment $assessment): bool
    {
        return ProjectMembershipEvent::query()
            ->where('student_id', $student->id)
            ->where('kind', ProjectMembershipEventKind::Leave)
            ->whereHas('project', fn ($q) => $q->where('project_assessment_id', $assessment->id))
            ->exists();
    }

    public function activeSeatCount(Project $project): int
    {
        return ProjectMembership::query()
            ->where('project_id', $project->id)
            ->whereNull('left_at')
            ->count();
    }

    public function applyMove(User $student, Project $from, Project $to): ProjectMembership
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['project' => [__('projects.same_team')]]);
        }

        $toLocked = Project::query()->whereKey($to->id)->lockForUpdate()->firstOrFail();
        $assessment = $toLocked->assessment()->firstOrFail();

        if ($this->activeSeatCount($toLocked) >= $assessment->team_size_max) {
            throw new ConflictException(__('projects.at_capacity'));
        }

        $current = $this->activeMembership($student, $from);
        if ($current === null) {
            throw new ConflictException(__('projects.not_member'));
        }

        $current->left_at = now();
        $current->save();

        $membership = ProjectMembership::query()->create([
            'project_id' => $toLocked->id,
            'student_id' => $student->id,
            'role' => ProjectMembershipRole::Member,
            'joined_at' => now(),
        ]);

        $this->recordEvent($toLocked, $student, ProjectMembershipEventKind::Move, [
            'from_project_id' => $from->id,
        ]);

        return $membership->fresh();
    }

    public function applyMerge(Project $source, Project $target): void
    {
        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['project' => [__('projects.same_team')]]);
        }

        $sourceLocked = Project::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
        $targetLocked = Project::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
        $assessment = $targetLocked->assessment()->firstOrFail();

        $incoming = $this->activeSeatCount($sourceLocked);
        if ($this->activeSeatCount($targetLocked) + $incoming > $assessment->team_size_max) {
            throw new ConflictException(__('projects.merge_capacity'));
        }

        $members = ProjectMembership::query()
            ->where('project_id', $sourceLocked->id)
            ->whereNull('left_at')
            ->lockForUpdate()
            ->get();

        foreach ($members as $membership) {
            $student = $membership->student;
            $membership->left_at = now();
            $membership->save();

            ProjectMembership::query()->create([
                'project_id' => $targetLocked->id,
                'student_id' => $membership->student_id,
                'role' => ProjectMembershipRole::Member,
                'joined_at' => now(),
            ]);

            if ($student !== null) {
                $this->recordEvent($targetLocked, $student, ProjectMembershipEventKind::Merge, [
                    'from_project_id' => $sourceLocked->id,
                ]);
            }
        }

        $sourceLocked->status = ProjectStatus::Merged;
        $sourceLocked->save();
    }

    public function assertEnrolled(User $student, ProjectAssessment $assessment): void
    {
        $enrolled = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('offering_id', $assessment->offering_id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->exists();

        if (! $enrolled) {
            throw new AuthorizationException(__('projects.not_enrolled'));
        }
    }

    private function seatOnTeam(ProjectAssessment $assessment, ?string $projectId): Project
    {
        if ($projectId !== null && $projectId !== '') {
            $project = Project::query()
                ->whereKey($projectId)
                ->where('project_assessment_id', $assessment->id)
                ->where('status', ProjectStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($project === null) {
                throw new ConflictException(__('projects.at_capacity'));
            }

            return $project;
        }

        $teams = Project::query()
            ->where('project_assessment_id', $assessment->id)
            ->where('status', ProjectStatus::Open)
            ->lockForUpdate()
            ->orderBy('created_at')
            ->get();

        foreach ($teams as $team) {
            if ($this->activeSeatCount($team) < $assessment->team_size_max) {
                return $team;
            }
        }

        $n = $teams->count() + 1;

        return Project::query()->create([
            'project_assessment_id' => $assessment->id,
            'name' => 'Team '.$n,
            'status' => ProjectStatus::Open,
        ]);
    }

    private function recordEvent(Project $project, User $student, ProjectMembershipEventKind $kind, array $meta = []): void
    {
        ProjectMembershipEvent::query()->create([
            'project_id' => $project->id,
            'student_id' => $student->id,
            'kind' => $kind,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    private function assertPublished(ProjectAssessment $assessment): void
    {
        if ($assessment->status !== ProjectAssessmentStatus::Published) {
            throw new ConflictException(__('projects.not_published'));
        }
    }

    private function assertNotLocked(ProjectAssessment $assessment): void
    {
        if ($assessment->status === ProjectAssessmentStatus::Locked) {
            throw new ConflictException(__('projects.locked'));
        }
    }

    private function assertOpen(Project $project): void
    {
        if ($project->status !== ProjectStatus::Open) {
            throw new ConflictException(__('projects.locked'));
        }
    }

    private function assertSameAssessment(Project $a, Project $b): void
    {
        if ($a->project_assessment_id !== $b->project_assessment_id) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }
}
