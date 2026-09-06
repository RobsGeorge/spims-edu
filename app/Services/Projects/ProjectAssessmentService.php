<?php

namespace App\Services\Projects;

use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectDeliverableKind;
use App\Enums\ProjectGradingMode;
use App\Models\CourseOffering;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectGradeCriterion;
use App\Models\ProjectPhase;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Validation\ValidationException;

class ProjectAssessmentService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, CourseOffering $offering, array $data): ProjectAssessment
    {
        $this->authorize->authorize($actor, 'projects.manage', $offering);

        return $this->audit->withAudit($actor, 'projects.assessment_create', function () use ($offering, $data) {
            return ProjectAssessment::query()->create([
                'offering_id' => $offering->id,
                'title' => $data['title'],
                'component_id' => $data['component_id'] ?? null,
                'team_size_min' => $data['team_size_min'] ?? 1,
                'team_size_max' => $data['team_size_max'] ?? 4,
                'join_opens_at' => $data['join_opens_at'] ?? null,
                'join_closes_at' => $data['join_closes_at'] ?? null,
                'allow_leave_once' => $data['allow_leave_once'] ?? true,
                'grading_mode' => $data['grading_mode'] ?? ProjectGradingMode::Rubric->value,
                'max_points' => $data['max_points'] ?? 100,
                'status' => ProjectAssessmentStatus::Draft,
                'peer_opens_at' => $data['peer_opens_at'] ?? null,
                'peer_closes_at' => $data['peer_closes_at'] ?? null,
            ]);
        }, 'ProjectAssessment');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, ProjectAssessment $assessment, array $data): ProjectAssessment
    {
        $this->authorize->authorize($actor, 'projects.manage', $assessment);
        $this->assertNotLocked($assessment);

        return $this->audit->withAudit($actor, 'projects.assessment_update', function () use ($assessment, $data) {
            $assessment->fill($data);
            $assessment->save();

            return $assessment->fresh();
        }, 'ProjectAssessment');
    }

    public function publish(User $actor, ProjectAssessment $assessment): ProjectAssessment
    {
        $this->authorize->authorize($actor, 'projects.manage', $assessment);

        if ($assessment->status !== ProjectAssessmentStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => [__('projects.invalid_transition', [
                    'from' => $assessment->status->value,
                    'to' => ProjectAssessmentStatus::Published->value,
                ])],
            ]);
        }

        $this->assertPublishable($assessment);

        return $this->audit->withAudit($actor, 'projects.assessment_publish', function () use ($assessment) {
            $assessment->status = ProjectAssessmentStatus::Published;
            $assessment->save();

            return $assessment->fresh();
        }, 'ProjectAssessment');
    }

    public function lock(User $actor, ProjectAssessment $assessment): ProjectAssessment
    {
        $this->authorize->authorize($actor, 'projects.manage', $assessment);

        if ($assessment->status !== ProjectAssessmentStatus::Published) {
            throw ValidationException::withMessages([
                'status' => [__('projects.invalid_transition', [
                    'from' => $assessment->status->value,
                    'to' => ProjectAssessmentStatus::Locked->value,
                ])],
            ]);
        }

        return $this->audit->withAudit($actor, 'projects.assessment_lock', function () use ($assessment) {
            $assessment->status = ProjectAssessmentStatus::Locked;
            $assessment->save();

            return $assessment->fresh();
        }, 'ProjectAssessment');
    }

    /**
     * @param  array{name: string, position?: int, due_at?: mixed}  $data
     */
    public function addPhase(User $actor, ProjectAssessment $assessment, array $data): ProjectPhase
    {
        $this->authorize->authorize($actor, 'projects.manage', $assessment);
        $this->assertNotLocked($assessment);

        return $this->audit->withAudit($actor, 'projects.phase_create', function () use ($assessment, $data) {
            $position = $data['position'] ?? ((int) $assessment->phases()->max('position') + 1);

            return ProjectPhase::query()->create([
                'project_assessment_id' => $assessment->id,
                'name' => $data['name'],
                'position' => $position,
                'due_at' => $data['due_at'] ?? null,
            ]);
        }, 'ProjectPhase');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addDeliverable(User $actor, ProjectPhase $phase, array $data): ProjectDeliverable
    {
        $phase->loadMissing('assessment');
        $this->authorize->authorize($actor, 'projects.manage', $phase->assessment);
        $this->assertNotLocked($phase->assessment);

        return $this->audit->withAudit($actor, 'projects.deliverable_create', function () use ($phase, $data) {
            return ProjectDeliverable::query()->create([
                'phase_id' => $phase->id,
                'kind' => $data['kind'] ?? ProjectDeliverableKind::File->value,
                'title' => $data['title'],
                'max_files' => $data['max_files'] ?? 1,
                'max_file_mb' => $data['max_file_mb'] ?? 10,
                'due_at' => $data['due_at'] ?? $phase->due_at,
                'points' => $data['points'] ?? 0,
            ]);
        }, 'ProjectDeliverable');
    }

    private function assertNotLocked(ProjectAssessment $assessment): void
    {
        if ($assessment->status === ProjectAssessmentStatus::Locked) {
            throw ValidationException::withMessages([
                'status' => [__('projects.locked')],
            ]);
        }
    }

    private function assertPublishable(ProjectAssessment $assessment): void
    {
        if ($assessment->grading_mode === ProjectGradingMode::Rubric) {
            $sum = (float) ProjectGradeCriterion::query()
                ->where('project_assessment_id', $assessment->id)
                ->sum('weight');
            if (abs($sum - 100.0) > 0.01) {
                throw ValidationException::withMessages([
                    'criteria' => [__('projects.criteria_required')],
                ]);
            }

            return;
        }

        $sum = 0.0;
        foreach ($assessment->phases()->with('deliverables')->get() as $phase) {
            $sum += (float) $phase->deliverables->sum('points');
        }
        if (abs($sum - (float) $assessment->max_points) > 0.01) {
            throw ValidationException::withMessages([
                'deliverables' => [__('projects.deliverables_required')],
            ]);
        }
    }
}
