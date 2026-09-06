<?php

namespace Tests\Feature\Projects;

use App\Enums\ComponentKind;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectDeliverableKind;
use App\Enums\ProjectGradingMode;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradebookComponent;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectGradeCriterion;
use App\Models\ProjectPhase;
use App\Models\User;
use App\Services\Projects\PeerEvaluationService;
use App\Services\Projects\ProjectAssessmentService;
use App\Services\Projects\ProjectChangeRequestService;
use App\Services\Projects\ProjectDeliverableService;
use App\Services\Projects\ProjectGradingService;
use App\Services\Projects\ProjectTeamService;

trait ProjectFixtures
{
    protected function offering(string $code = 'PRJ1'): CourseOffering
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => "Course $code",
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        return CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
    }

    protected function enroll(User $student, CourseOffering $offering): Enrollment
    {
        return Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);
    }

    protected function instructorOn(CourseOffering $offering): User
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return $instructor;
    }

    protected function studentOn(CourseOffering $offering): User
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        return $student;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function publishedAssessment(CourseOffering $offering, array $overrides = []): ProjectAssessment
    {
        return ProjectAssessment::query()->create(array_merge([
            'offering_id' => $offering->id,
            'title' => 'Capstone',
            'team_size_min' => 1,
            'team_size_max' => 2,
            'join_opens_at' => now()->subHour(),
            'join_closes_at' => now()->addHour(),
            'allow_leave_once' => true,
            'grading_mode' => ProjectGradingMode::Rubric,
            'max_points' => 100,
            'status' => ProjectAssessmentStatus::Published,
            'peer_opens_at' => now()->subHour(),
            'peer_closes_at' => now()->addHour(),
        ], $overrides));
    }

    protected function projectComponent(CourseOffering $offering): GradebookComponent
    {
        return GradebookComponent::query()->create([
            'offering_id' => $offering->id,
            'name' => 'Project',
            'weight_percent' => 100,
            'kind' => ComponentKind::Project,
        ]);
    }

    protected function fileDeliverable(ProjectAssessment $assessment, array $overrides = []): ProjectDeliverable
    {
        $phase = ProjectPhase::query()->create([
            'project_assessment_id' => $assessment->id,
            'name' => 'Phase 1',
            'position' => 1,
            'due_at' => now()->addDay(),
        ]);

        return ProjectDeliverable::query()->create(array_merge([
            'phase_id' => $phase->id,
            'kind' => ProjectDeliverableKind::File,
            'title' => 'Report',
            'max_files' => 1,
            'max_file_mb' => 1,
            'due_at' => now()->addDay(),
            'points' => 100,
        ], $overrides));
    }

    protected function teams(): ProjectTeamService
    {
        return app(ProjectTeamService::class);
    }

    protected function assessments(): ProjectAssessmentService
    {
        return app(ProjectAssessmentService::class);
    }

    protected function deliverables(): ProjectDeliverableService
    {
        return app(ProjectDeliverableService::class);
    }

    protected function grading(): ProjectGradingService
    {
        return app(ProjectGradingService::class);
    }

    protected function peers(): PeerEvaluationService
    {
        return app(PeerEvaluationService::class);
    }

    protected function changeRequests(): ProjectChangeRequestService
    {
        return app(ProjectChangeRequestService::class);
    }

    protected function joinTeam(User $student, ProjectAssessment $assessment, ?Project $project = null): Project
    {
        $membership = $this->teams()->join($student, $assessment, $project?->id);

        return $membership->project()->firstOrFail();
    }

    protected function addCriterion(ProjectAssessment $assessment, string $name = 'Quality', float $weight = 100): ProjectGradeCriterion
    {
        return ProjectGradeCriterion::query()->create([
            'project_assessment_id' => $assessment->id,
            'name' => $name,
            'weight' => $weight,
            'level' => 'TEAM',
        ]);
    }
}
