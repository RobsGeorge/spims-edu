<?php

namespace Tests\Feature\Auth;

use App\Enums\ComponentKind;
use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradebookComponent;
use App\Models\LiveSession;
use App\Models\User;
use App\Models\Week;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AssignmentService;
use App\Services\Discussions\DiscussionService;
use App\Services\Gradebook\GradebookService;
use App\Services\Live\AttendanceService;
use App\Services\Live\LiveSessionService;
use App\Services\Offerings\OfferingService;
use App\Support\AuthorizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The scope invariant: holding Instructor or TA is authority over the offerings you are
 * staffed on, and nothing else. Cross-offering access must fail everywhere.
 *
 * This is the suite that has to stay green — a new offering-owned route added without a
 * scope check should surface here.
 */
class ResourceScopeTest extends TestCase
{
    use RefreshDatabase;

    private function offering(string $code): CourseOffering
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
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
    }

    private function assertDenied(callable $operation, string $label): void
    {
        try {
            $operation();
            $this->fail("Expected [$label] to be denied across offerings, but it was allowed.");
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function an_instructor_cannot_act_on_an_offering_they_do_not_staff(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $mine = $this->offering('MINE');
        $theirs = $this->offering('THEIRS');
        $this->staffOffering($instructor, $mine);

        $week = Week::query()->create([
            'offering_id' => $theirs->id, 'number' => 1, 'title' => 'W1', 'order' => 1,
        ]);
        $item = ContentItem::query()->create([
            'week_id' => $week->id, 'type' => ContentItemType::Assignment, 'title' => 'A', 'order' => 1,
        ]);
        $session = LiveSession::query()->create([
            'offering_id' => $theirs->id,
            'title' => 'Lecture',
            'scheduled_start' => now()->addDay(),
            'duration_minutes' => 60,
        ]);

        $operations = [
            'gradebook.addComponent' => fn () => app(GradebookService::class)
                ->addComponent($instructor, $theirs, ['name' => 'X', 'weight_percent' => 10, 'kind' => ComponentKind::Exam->value]),
            'gradebook.seedFromTemplate' => fn () => app(GradebookService::class)
                ->seedFromTemplate($instructor, $theirs),
            'gradebook.submitGrades' => fn () => app(GradebookService::class)
                ->submitGrades($instructor, $theirs),
            'gradebook.lockGrades' => fn () => app(GradebookService::class)
                ->lockGrades($instructor, $theirs),
            'assessments.create' => fn () => app(AssessmentService::class)
                ->create($instructor, $theirs, ['title' => 'Quiz', 'mode' => 'QUIZ']),
            'assignments.create' => fn () => app(AssignmentService::class)
                ->create($instructor, $item, ['instructions' => 'Do it']),
            'offerings.addWeek' => fn () => app(OfferingService::class)
                ->addWeek($instructor, $theirs, ['number' => 2, 'title' => 'W2']),
            'offerings.addContentItem' => fn () => app(OfferingService::class)
                ->addContentItem($instructor, $week, ['type' => ContentItemType::Text->value, 'title' => 'T']),
            'live.schedule' => fn () => app(LiveSessionService::class)
                ->schedule($instructor, $theirs, ['title' => 'L', 'scheduled_start' => now()->addDays(2), 'duration_minutes' => 60]),
            'attendance.import' => fn () => app(AttendanceService::class)
                ->importFromZoom($instructor, $session, []),
            'discussions.configure' => fn () => app(DiscussionService::class)
                ->configureBoard($instructor, $theirs, true),
        ];

        foreach ($operations as $label => $operation) {
            $this->assertDenied($operation, $label);
        }
    }

    #[Test]
    public function the_same_instructor_can_act_on_the_offering_they_do_staff(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $mine = $this->offering('MINE');
        $this->staffOffering($instructor, $mine);

        $component = app(GradebookService::class)->addComponent($instructor, $mine, [
            'name' => 'Exams', 'weight_percent' => 50, 'kind' => ComponentKind::Exam->value,
        ]);

        $this->assertInstanceOf(GradebookComponent::class, $component);
        $this->assertSame($mine->id, $component->offering_id);
    }

    #[Test]
    public function an_academic_admin_is_not_confined_to_staffed_offerings(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $offering = $this->offering('ANY');

        $component = app(GradebookService::class)->addComponent($admin, $offering, [
            'name' => 'Exams', 'weight_percent' => 50, 'kind' => ComponentKind::Exam->value,
        ]);

        $this->assertSame($offering->id, $component->offering_id);
    }

    #[Test]
    public function a_scoped_action_with_no_resource_fails_closed(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $offering = $this->offering('MINE');
        $this->staffOffering($instructor, $offering);

        // Even though this instructor staffs an offering, omitting the resource must not
        // be read as "any offering".
        $this->expectException(AuthorizationException::class);
        app(AuthorizeService::class)->authorize($instructor, 'gradebook.configure');
    }

    #[Test]
    public function self_scoped_own_permissions_still_work_without_a_resource(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $authorize = app(AuthorizeService::class);

        // These are `O` in the matrix but mean "on my own behalf", not "an offering I staff".
        foreach (['profile.edit_own', 'finance.pay', 'assignments.submit', 'assessments.take', 'enrollment.register'] as $key) {
            $authorize->authorize($student, $key);
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function a_ta_is_scoped_the_same_way_and_still_cannot_lock_grades(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $mine = $this->offering('TA1');
        $theirs = $this->offering('TA2');
        $this->staffOffering($ta, $mine, OfferingStaffRole::Ta);

        // Scoped out of another offering entirely.
        $this->assertDenied(
            fn () => app(GradebookService::class)->addComponent($ta, $theirs, [
                'name' => 'X', 'weight_percent' => 10, 'kind' => ComponentKind::Exam->value,
            ]),
            'ta.addComponent cross-offering'
        );

        // And denied the lock even on their own offering: the matrix grants `lock` to
        // Instructor only.
        $this->assertDenied(
            fn () => app(GradebookService::class)->lockGrades($ta, $mine),
            'ta.lockGrades own offering'
        );
    }

    #[Test]
    public function superadmin_bypasses_scope_entirely(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $super = User::factory()->withRole(RoleType::SuperAdmin)->create();
        $offering = $this->offering('SUP');

        $component = app(GradebookService::class)->addComponent($super, $offering, [
            'name' => 'Exams', 'weight_percent' => 50, 'kind' => ComponentKind::Exam->value,
        ]);

        $this->assertSame($offering->id, $component->offering_id);
    }

    #[Test]
    public function admin_routes_are_scoped_not_merely_role_gated(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $mine = $this->offering('RT1');
        $theirs = $this->offering('RT2');
        $this->staffOffering($instructor, $mine);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $theirs->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        // The route middleware alone used to be a role check; it must now scope.
        $this->actingAs($instructor)
            ->post(route('admin.gradebook.lock', $theirs))
            ->assertForbidden();

        $this->actingAs($instructor)
            ->get(route('admin.gradebook.show', $theirs))
            ->assertForbidden();
    }
}
