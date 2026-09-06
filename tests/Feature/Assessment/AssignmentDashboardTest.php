<?php

namespace Tests\Feature\Assessment;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Assignment;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use App\Services\Assessment\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssignmentDashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dashboard_stats_match_a_hand_constructed_fixture_exactly(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        [$a, $b, $c] = User::factory()->withRole(RoleType::Student)->count(3)->create();

        $course = Course::query()->create([
            'code' => 'DASH1',
            'title' => 'Dashboard Course',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        $this->staffOffering($instructor, $offering);

        foreach ([$a, $b, $c] as $student) {
            Enrollment::query()->create([
                'student_id' => $student->id,
                'offering_id' => $offering->id,
                'status' => EnrollmentStatus::Enrolled,
                'enrolled_at' => now(),
            ]);
        }

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week 1',
            'order' => 1,
        ]);
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Assignment,
            'title' => 'Essay',
            'order' => 1,
        ]);

        // Due date is in the past: student C never submits and is overdue; A and B
        // submitted (one of them late), so neither counts as overdue even though A's
        // submission was late.
        $assignment = Assignment::query()->create([
            'content_item_id' => $item->id,
            'instructions' => 'Write an essay.',
            'allowed_file_types' => ['pdf'],
            'max_points' => 100,
            'due_date' => now()->subDays(2),
        ]);

        $service = app(AssignmentService::class);
        $service->submit($a, $assignment, textBody: 'A draft');
        $subB = $service->submit($b, $assignment, textBody: 'B draft');

        // Grade only B, leaving A ungraded and C entirely unsubmitted.
        $service->grade($instructor, $subB, 90.0);

        $stats = $service->dashboardStats($instructor, $offering);

        $this->assertCount(1, $stats);
        $row = $stats[0];
        $this->assertSame($assignment->id, $row['assignment_id']);
        $this->assertSame('ONLINE', $row['delivery_mode']);
        $this->assertSame(3, $row['enrolled_count']);
        $this->assertSame(2, $row['submitted_count']);
        $this->assertSame(1, $row['ungraded_count'], 'Only A remains ungraded.');
        $this->assertSame(1, $row['overdue_count'], 'Only C never submitted while overdue.');
    }

    #[Test]
    public function academic_admin_sees_the_dashboard_school_wide_without_staffing(): void
    {
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $course = Course::query()->create([
            'code' => 'DASH2',
            'title' => 'Dashboard Course 2',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);

        $stats = app(AssignmentService::class)->dashboardStats($admin, $offering);

        $this->assertSame([], $stats);
    }
}
