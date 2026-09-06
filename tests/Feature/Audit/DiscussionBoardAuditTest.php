<?php

namespace Tests\Feature\Audit;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Discussions\DiscussionService;
use App\Services\Offerings\OfferingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscussionBoardAuditTest extends TestCase
{
    use RefreshDatabase;

    private function offering(OfferingMode $mode = OfferingMode::SelfPaced): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'DISC1',
            'title' => 'Discussion Course',
            'credit_hours' => 2,
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

    #[Test]
    public function visiting_the_board_for_the_first_time_does_not_create_a_board_or_audit_row(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offering();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $this->assertDatabaseCount('discussion_boards', 0);
        $this->assertDatabaseCount('audit_logs', 0);

        $this->actingAs($student)
            ->get(route('discussions.board', $offering))
            ->assertOk();

        $this->assertDatabaseCount('discussion_boards', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseMissing('discussion_boards', ['offering_id' => $offering->id]);
    }

    #[Test]
    public function creating_an_offering_provisions_exactly_one_audited_board(): void
    {
        $actor = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $course = Course::query()->create([
            'code' => 'DISC2',
            'title' => 'Discussion Course 2',
            'credit_hours' => 2,
            'is_standalone' => true,
            'active' => true,
        ]);

        $offering = app(OfferingService::class)->create($actor, [
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced->value,
        ]);

        $this->assertDatabaseCount('discussion_boards', 1);
        $this->assertDatabaseHas('discussion_boards', ['offering_id' => $offering->id]);

        $this->assertSame(
            1,
            AuditLog::query()
                ->where('action', 'discussions.board_provision')
                ->where('entity_type', 'DiscussionBoard')
                ->count()
        );
    }

    #[Test]
    public function posting_the_first_thread_provisions_the_board_exactly_once(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offering(OfferingMode::Cohort);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $this->assertDatabaseCount('discussion_boards', 0);

        $this->actingAs($student)
            ->post(route('discussions.threads.store', $offering), [
                'title' => 'First thread',
                'body' => 'Hello everyone',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('discussion_boards', 1);
        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'discussions.board_provision')->count()
        );

        // Idempotency: provisioning again must not create a second board or audit row.
        app(DiscussionService::class)->provisionBoard($student, $offering);

        $this->assertDatabaseCount('discussion_boards', 1);
        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'discussions.board_provision')->count()
        );
    }

    #[Test]
    public function configuring_a_board_with_none_existing_provisions_and_configures_in_one_call(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $offering = $this->offering(OfferingMode::Cohort);
        $this->staffOffering($instructor, $offering);

        $this->assertDatabaseCount('discussion_boards', 0);

        $board = app(DiscussionService::class)->configureBoard($instructor, $offering, false);

        $this->assertDatabaseCount('discussion_boards', 1);
        $this->assertFalse($board->allow_student_threads);

        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'discussions.board_provision')->count()
        );
        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'discussions.configure')->count()
        );
    }
}
