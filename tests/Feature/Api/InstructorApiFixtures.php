<?php

namespace Tests\Feature\Api;

use App\Enums\ClassSessionMode;
use App\Enums\CompletionCriterionKind;
use App\Enums\ComponentKind;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectGradingMode;
use App\Enums\ProjectStatus;
use App\Enums\RoleType;
use App\Enums\ThreadVisibility;
use App\Models\Announcement;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\DiscussionBoard;
use App\Models\DiscussionGrade;
use App\Models\DiscussionThread;
use App\Models\Enrollment;
use App\Models\GradebookComponent;
use App\Models\LiveQuiz;
use App\Models\LiveQuizQuestion;
use App\Models\LiveQuizSession;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\User;
use App\Models\Week;
use App\Services\Communications\AnnouncementService;
use App\Services\Completion\CompletionService;
use App\Services\Completion\OfferingClosingService;
use App\Services\Live\AttendanceService;
use App\Services\LiveQuiz\LiveQuizHostService;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\Auth;

trait InstructorApiFixtures
{
    protected function apiToken(User $user, string $role): string
    {
        return $user->createToken('api', ["role:{$role}"])->plainTextToken;
    }

    protected function asApi(User $user, string $role)
    {
        Auth::forgetGuards();

        return $this->withToken($this->apiToken($user, $role));
    }

    protected function offering(string $code, OfferingMode $mode = OfferingMode::Cohort): CourseOffering
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $course = Course::query()->create([
            'code' => $code,
            'title' => "Course $code",
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        return CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => $mode,
            'status' => 'OPEN',
            'attendance_threshold_percent' => 60,
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

    protected function taOn(CourseOffering $offering): User
    {
        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $this->staffOffering($ta, $offering, OfferingStaffRole::Ta);

        return $ta;
    }

    /**
     * Two offerings: instructor A (and a TA) staffed only on A; B has its own instructor
     * and a full set of teach-route records so scope cases can name every path param.
     *
     * @return array{
     *     offeringA: CourseOffering,
     *     offeringB: CourseOffering,
     *     instructorA: User,
     *     instructorB: User,
     *     taA: User,
     *     admin: User,
     *     studentA: User,
     *     studentB: User,
     *     sessionB: ClassSession,
     *     announcementB: Announcement,
     *     weekB: Week,
     *     liveQuizB: LiveQuiz,
     *     liveQuizSessionB: LiveQuizSession,
     *     liveQuizQuestionB: LiveQuizQuestion,
     *     projectAssessmentB: ProjectAssessment,
     *     projectB: Project
     * }
     */
    protected function staffTwoOfferings(): array
    {
        app(AuthorizeService::class)->forgetMatrixCache();

        $offeringA = $this->offering('S8A');
        $offeringB = $this->offering('S8B');

        $instructorA = $this->instructorOn($offeringA);
        $instructorB = $this->instructorOn($offeringB);
        $taA = $this->taOn($offeringA);
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $studentA = User::factory()->withRole(RoleType::Student)->create();
        $studentB = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($studentA, $offeringA);
        $this->enroll($studentB, $offeringB);

        $sessionB = app(AttendanceService::class)->openSession($instructorB, $offeringB, [
            'title' => 'B lecture',
            'scheduled_start' => now()->addHour(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);

        $announcementB = app(AnnouncementService::class)->draft($instructorB, $offeringB, [
            'title' => 'B draft',
            'body' => 'B body',
        ]);

        $weekB = Week::query()->create([
            'offering_id' => $offeringB->id,
            'number' => 1,
            'title' => 'Week 1',
            'order' => 1,
        ]);

        $liveQuizB = app(LiveQuizHostService::class)->createQuiz($instructorB, $offeringB, 'B quiz', [
            [
                'prompt' => '2+2?',
                'time_limit_seconds' => 30,
                'points' => 100,
                'options' => [
                    ['label' => '3', 'is_correct' => false],
                    ['label' => '4', 'is_correct' => true],
                ],
            ],
        ]);
        $liveQuizQuestionB = $liveQuizB->questions()->firstOrFail();
        $liveQuizSessionB = app(LiveQuizHostService::class)->startSession($instructorB, $liveQuizB);

        $projectAssessmentB = ProjectAssessment::query()->create([
            'offering_id' => $offeringB->id,
            'title' => 'B capstone',
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
        ]);
        $projectB = Project::query()->create([
            'project_assessment_id' => $projectAssessmentB->id,
            'name' => 'Team B',
            'status' => ProjectStatus::Open,
        ]);

        return compact(
            'offeringA',
            'offeringB',
            'instructorA',
            'instructorB',
            'taA',
            'admin',
            'studentA',
            'studentB',
            'sessionB',
            'announcementB',
            'weekB',
            'liveQuizB',
            'liveQuizSessionB',
            'liveQuizQuestionB',
            'projectAssessmentB',
            'projectB',
        );
    }

    protected function setDiscussionGrade(CourseOffering $offering, User $student, float $percent): void
    {
        GradebookComponent::query()->firstOrCreate(
            ['offering_id' => $offering->id, 'name' => 'Discussion'],
            ['weight_percent' => 100, 'kind' => ComponentKind::Discussion]
        );

        $board = DiscussionBoard::query()->firstOrCreate(
            ['offering_id' => $offering->id],
            ['allow_student_threads' => true]
        );

        $thread = DiscussionThread::query()->firstOrCreate(
            ['board_id' => $board->id, 'title' => 'Graded'],
            [
                'author_id' => $student->id,
                'visibility' => ThreadVisibility::Open,
                'is_graded' => true,
                'locked' => false,
                'pinned' => false,
            ]
        );

        DiscussionGrade::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'student_id' => $student->id],
            ['final_score' => $percent, 'auto_score' => $percent]
        );
    }

    protected function announceReady(User $admin, CourseOffering $offering, User $student): void
    {
        $this->setDiscussionGrade($offering, $student, 90);
        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);

        $closing = app(OfferingClosingService::class);
        $closing->lockGrading($admin, $offering);
        $closing->announce($admin, $offering);
    }
}
