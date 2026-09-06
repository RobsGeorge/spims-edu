<?php

namespace App\Services\Projects;

use App\Enums\ProjectGradeLevel;
use App\Enums\ProjectGradingMode;
use App\Models\GradebookComponent;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectGrade;
use App\Models\ProjectGradeCriterion;
use App\Models\ProjectMembership;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Validation\ValidationException;

class ProjectGradingService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly ProjectTeamService $teams,
    ) {}

    /**
     * @param  array<int, array{name: string, weight: float|int, level?: string}>  $rows
     * @return array<int, ProjectGradeCriterion>
     */
    public function setCriteria(User $actor, ProjectAssessment $assessment, array $rows): array
    {
        $this->authorize->authorize($actor, 'projects.grade', $assessment);

        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) $row['weight'];
        }
        if (abs($sum - 100.0) > 0.01) {
            throw ValidationException::withMessages([
                'criteria' => [__('projects.weights_sum')],
            ]);
        }

        $this->audit->withAudit($actor, 'projects.criteria_set', function () use ($assessment, $rows) {
            ProjectGradeCriterion::query()->where('project_assessment_id', $assessment->id)->delete();

            $first = null;
            foreach ($rows as $row) {
                $created = ProjectGradeCriterion::query()->create([
                    'project_assessment_id' => $assessment->id,
                    'name' => $row['name'],
                    'weight' => $row['weight'],
                    'level' => $row['level'] ?? ProjectGradeLevel::Team->value,
                ]);
                $first ??= $created;
            }

            return $first ?? $assessment;
        }, 'ProjectGradeCriterion');

        return $this->criteriaOf($assessment);
    }

    /**
     * @return array<int, ProjectGradeCriterion>
     */
    private function criteriaOf(ProjectAssessment $assessment): array
    {
        return ProjectGradeCriterion::query()
            ->where('project_assessment_id', $assessment->id)
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function setTeamScore(User $actor, Project $project, float $score, ?string $criterionId = null): ProjectGrade
    {
        $project->loadMissing('assessment');
        $this->authorize->authorize($actor, 'projects.grade', $project->assessment);

        return $this->audit->withAudit($actor, 'projects.team_score', function () use ($project, $score, $criterionId) {
            $grade = $this->upsertGrade(
                $project->project_assessment_id,
                $project->id,
                null,
                $criterionId,
                $score,
            );

            if ($criterionId !== null) {
                $this->refreshOverallTeamScore($project);
            }

            return $grade;
        }, 'ProjectGrade');
    }

    public function setStudentOverride(
        User $actor,
        ProjectAssessment $assessment,
        User $student,
        float $score,
        ?string $criterionId = null,
        ?string $projectId = null,
    ): ProjectGrade {
        $this->authorize->authorize($actor, 'projects.grade', $assessment);

        $projectId ??= $this->teams->activeMembershipForAssessment($student, $assessment)?->project_id;

        return $this->audit->withAudit($actor, 'projects.student_override', function () use ($assessment, $student, $score, $criterionId, $projectId) {
            $grade = $this->upsertGrade(
                $assessment->id,
                $projectId,
                $student->id,
                $criterionId,
                $score,
            );

            if ($criterionId !== null) {
                $this->refreshOverallStudentScore($assessment, $student, $projectId);
            }

            return $grade;
        }, 'ProjectGrade');
    }

    public function announce(User $actor, ProjectAssessment $assessment): int
    {
        $this->authorize->authorize($actor, 'projects.announce', $assessment);

        $pending = ProjectGrade::query()
            ->where('project_assessment_id', $assessment->id)
            ->whereNull('announced_at')
            ->count();

        if ($pending === 0) {
            return 0;
        }

        $this->audit->withAudit($actor, 'projects.announce', function () use ($assessment) {
            ProjectGrade::query()
                ->where('project_assessment_id', $assessment->id)
                ->whereNull('announced_at')
                ->update(['announced_at' => now()]);

            return $assessment;
        }, 'ProjectAssessment');

        return $pending;
    }

    /**
     * Record a team score and/or per-student overrides for one project.
     *
     * @param  array{
     *     team_score?: float|int|null,
     *     students?: array<int, array{student_id: string, score: float|int}>,
     *     criterion_id?: string|null
     * }  $data
     * @return array{team: ?ProjectGrade, students: array<int, ProjectGrade>}
     */
    public function recordScores(User $actor, Project $project, array $data): array
    {
        $project->loadMissing('assessment');
        $this->authorize->authorize($actor, 'projects.grade', $project->assessment);

        $hasTeam = array_key_exists('team_score', $data) && $data['team_score'] !== null && $data['team_score'] !== '';
        $students = $data['students'] ?? [];
        if (! $hasTeam && $students === []) {
            throw ValidationException::withMessages([
                'scores' => [__('projects.scores_required')],
            ]);
        }

        $criterionId = $data['criterion_id'] ?? null;
        $team = null;
        $studentGrades = [];

        if ($hasTeam) {
            $team = $this->setTeamScore($actor, $project, (float) $data['team_score'], $criterionId);
        }

        foreach ($students as $row) {
            $student = User::query()->findOrFail($row['student_id']);
            $studentGrades[] = $this->setStudentOverride(
                $actor,
                $project->assessment,
                $student,
                (float) $row['score'],
                $criterionId,
                $project->id,
            );
        }

        return ['team' => $team, 'students' => $studentGrades];
    }

    public function announcedPercentForComponent(GradebookComponent $component, User $student): ?float
    {
        $assessments = ProjectAssessment::query()
            ->where('component_id', $component->id)
            ->get();

        $pcts = [];
        foreach ($assessments as $assessment) {
            $pct = $this->announcedPercentForStudent($assessment, $student);
            if ($pct !== null) {
                $pcts[] = $pct;
            }
        }

        if ($pcts === []) {
            return null;
        }

        return round(array_sum($pcts) / count($pcts), 2);
    }

    /**
     * Announced overall percent for one student. Per-student override beats team.
     * Peer evaluations are never consulted.
     */
    public function announcedPercentForStudent(ProjectAssessment $assessment, User $student): ?float
    {
        $override = ProjectGrade::query()
            ->where('project_assessment_id', $assessment->id)
            ->where('student_id', $student->id)
            ->whereNull('criterion_id')
            ->whereNotNull('announced_at')
            ->first();

        if ($override !== null) {
            return $this->toPercent($assessment, (float) $override->score);
        }

        $projectId = $this->projectIdForStudent($assessment, $student);
        if ($projectId === null) {
            return $this->announcedCriterionPercent($assessment, $student, null);
        }

        $team = ProjectGrade::query()
            ->where('project_assessment_id', $assessment->id)
            ->where('project_id', $projectId)
            ->whereNull('student_id')
            ->whereNull('criterion_id')
            ->whereNotNull('announced_at')
            ->first();

        if ($team !== null) {
            return $this->toPercent($assessment, (float) $team->score);
        }

        return $this->announcedCriterionPercent($assessment, $student, $projectId);
    }

    private function announcedCriterionPercent(ProjectAssessment $assessment, User $student, ?string $projectId): ?float
    {
        $criteria = ProjectGradeCriterion::query()
            ->where('project_assessment_id', $assessment->id)
            ->get();

        if ($criteria->isEmpty()) {
            return null;
        }

        $weighted = 0.0;
        $weightSum = 0.0;

        foreach ($criteria as $criterion) {
            $row = ProjectGrade::query()
                ->where('project_assessment_id', $assessment->id)
                ->where('criterion_id', $criterion->id)
                ->where('student_id', $student->id)
                ->whereNotNull('announced_at')
                ->first();

            if ($row === null && $projectId !== null) {
                $row = ProjectGrade::query()
                    ->where('project_assessment_id', $assessment->id)
                    ->where('criterion_id', $criterion->id)
                    ->where('project_id', $projectId)
                    ->whereNull('student_id')
                    ->whereNotNull('announced_at')
                    ->first();
            }

            if ($row === null) {
                continue;
            }

            $weighted += $this->toPercent($assessment, (float) $row->score) * ((float) $criterion->weight / 100);
            $weightSum += (float) $criterion->weight;
        }

        if ($weightSum <= 0) {
            return null;
        }

        return round($weighted / ($weightSum / 100), 2);
    }

    private function upsertGrade(
        string $assessmentId,
        ?string $projectId,
        ?string $studentId,
        ?string $criterionId,
        float $score,
    ): ProjectGrade {
        $query = ProjectGrade::query()
            ->where('project_assessment_id', $assessmentId)
            ->when($projectId === null, fn ($q) => $q->whereNull('project_id'), fn ($q) => $q->where('project_id', $projectId))
            ->when($studentId === null, fn ($q) => $q->whereNull('student_id'), fn ($q) => $q->where('student_id', $studentId))
            ->when($criterionId === null, fn ($q) => $q->whereNull('criterion_id'), fn ($q) => $q->where('criterion_id', $criterionId));

        $existing = $query->first();
        if ($existing !== null) {
            $existing->score = $score;
            $existing->save();

            return $existing->fresh();
        }

        return ProjectGrade::query()->create([
            'project_assessment_id' => $assessmentId,
            'project_id' => $projectId,
            'student_id' => $studentId,
            'criterion_id' => $criterionId,
            'score' => $score,
        ]);
    }

    private function refreshOverallTeamScore(Project $project): void
    {
        $overall = $this->weightedScore($project->project_assessment_id, $project->id, null);
        if ($overall === null) {
            return;
        }

        $this->upsertGrade($project->project_assessment_id, $project->id, null, null, $overall);
    }

    private function refreshOverallStudentScore(ProjectAssessment $assessment, User $student, ?string $projectId): void
    {
        $overall = $this->weightedScore($assessment->id, $projectId, $student->id);
        if ($overall === null) {
            return;
        }

        $this->upsertGrade($assessment->id, $projectId, $student->id, null, $overall);
    }

    private function weightedScore(string $assessmentId, ?string $projectId, ?string $studentId): ?float
    {
        $criteria = ProjectGradeCriterion::query()->where('project_assessment_id', $assessmentId)->get();
        if ($criteria->isEmpty()) {
            return null;
        }

        $weighted = 0.0;
        $weightSum = 0.0;
        foreach ($criteria as $criterion) {
            $row = ProjectGrade::query()
                ->where('project_assessment_id', $assessmentId)
                ->where('criterion_id', $criterion->id)
                ->when($studentId === null, fn ($q) => $q->whereNull('student_id'), fn ($q) => $q->where('student_id', $studentId))
                ->when($projectId === null, fn ($q) => $q->whereNull('project_id'), fn ($q) => $q->where('project_id', $projectId))
                ->first();
            if ($row === null) {
                continue;
            }
            $weighted += (float) $row->score * ((float) $criterion->weight / 100);
            $weightSum += (float) $criterion->weight;
        }

        if ($weightSum <= 0) {
            return null;
        }

        return round($weighted / ($weightSum / 100), 4);
    }

    private function toPercent(ProjectAssessment $assessment, float $score): float
    {
        if ($assessment->grading_mode === ProjectGradingMode::Deliverables && $assessment->max_points > 0) {
            if ($score <= $assessment->max_points) {
                return round(($score / (float) $assessment->max_points) * 100, 2);
            }
        }

        return round($score, 2);
    }

    private function projectIdForStudent(ProjectAssessment $assessment, User $student): ?string
    {
        $active = $this->teams->activeMembershipForAssessment($student, $assessment);
        if ($active !== null) {
            return $active->project_id;
        }

        return ProjectMembership::query()
            ->where('student_id', $student->id)
            ->whereHas('project', fn ($q) => $q->where('project_assessment_id', $assessment->id))
            ->orderByDesc('joined_at')
            ->value('project_id');
    }
}
