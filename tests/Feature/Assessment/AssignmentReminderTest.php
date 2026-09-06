<?php

namespace Tests\Feature\Assessment;

use App\Enums\ContentItemType;
use App\Enums\DeliveryMode;
use App\Enums\EnrollmentStatus;
use App\Enums\NotificationChannel;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Assignment;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\User;
use App\Models\Week;
use App\Services\Assessment\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssignmentReminderTest extends TestCase
{
    use RefreshDatabase;

    private function bundle(DeliveryMode $mode = DeliveryMode::Online): array
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        [$submitted, $unsubmittedOne, $unsubmittedTwo] = User::factory()->withRole(RoleType::Student)->count(3)->create();

        $course = Course::query()->create([
            'code' => 'REM1',
            'title' => 'Reminder Course',
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

        foreach ([$submitted, $unsubmittedOne, $unsubmittedTwo] as $student) {
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

        $assignment = Assignment::query()->create([
            'content_item_id' => $item->id,
            'instructions' => 'Write an essay.',
            'allowed_file_types' => ['pdf'],
            'max_points' => 100,
            'delivery_mode' => $mode,
        ]);

        return compact('instructor', 'submitted', 'unsubmittedOne', 'unsubmittedTwo', 'offering', 'assignment');
    }

    #[Test]
    public function only_unsubmitted_students_are_reminded_once_per_window(): void
    {
        ['instructor' => $instructor, 'submitted' => $submitted, 'unsubmittedOne' => $u1, 'unsubmittedTwo' => $u2, 'assignment' => $assignment] = $this->bundle();

        app(AssignmentService::class)->submit($submitted, $assignment, textBody: 'done');

        $count = app(AssignmentService::class)->remindUnsubmitted($instructor, $assignment);

        $this->assertSame(2, $count);
        $this->assertSame(2, Notification::query()->where('type', 'assignments.reminder')->where('channel', NotificationChannel::InApp)->count());
        $this->assertTrue(Notification::query()->where('user_id', $u1->id)->where('type', 'assignments.reminder')->exists());
        $this->assertTrue(Notification::query()->where('user_id', $u2->id)->where('type', 'assignments.reminder')->exists());
        $this->assertFalse(Notification::query()->where('user_id', $submitted->id)->where('type', 'assignments.reminder')->exists());

        // Calling it again in the same window must not double-send to students
        // already reminded.
        $countAgain = app(AssignmentService::class)->remindUnsubmitted($instructor, $assignment);
        $this->assertSame(0, $countAgain);
        $this->assertSame(2, Notification::query()->where('type', 'assignments.reminder')->where('channel', NotificationChannel::InApp)->count());
    }

    #[Test]
    public function a_student_who_submits_after_being_reminded_is_not_reminded_again(): void
    {
        ['instructor' => $instructor, 'submitted' => $submitted, 'unsubmittedOne' => $u1, 'unsubmittedTwo' => $u2, 'assignment' => $assignment] = $this->bundle();

        app(AssignmentService::class)->submit($submitted, $assignment, textBody: 'already done');
        app(AssignmentService::class)->remindUnsubmitted($instructor, $assignment);
        $this->assertSame(2, Notification::query()->where('type', 'assignments.reminder')->where('channel', NotificationChannel::InApp)->count());

        app(AssignmentService::class)->submit($u1, $assignment, textBody: 'late but done');

        $count = app(AssignmentService::class)->remindUnsubmitted($instructor, $assignment);
        $this->assertSame(0, $count, 'u1 now has a submission; u2 was already reminded.');
        $this->assertSame(2, Notification::query()->where('type', 'assignments.reminder')->where('channel', NotificationChannel::InApp)->count());
    }

    #[Test]
    public function offline_assignments_are_excluded_from_reminders(): void
    {
        // A physical hand-in has no digital act to remind a student to perform; the
        // completion signal (markReceived) is staff-driven, not student-driven.
        ['instructor' => $instructor, 'assignment' => $assignment] = $this->bundle(DeliveryMode::Offline);

        $count = app(AssignmentService::class)->remindUnsubmitted($instructor, $assignment);

        $this->assertSame(0, $count);
        $this->assertSame(0, Notification::query()->where('type', 'assignments.reminder')->where('channel', NotificationChannel::InApp)->count());
    }
}
