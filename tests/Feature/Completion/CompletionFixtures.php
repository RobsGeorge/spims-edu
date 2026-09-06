<?php

namespace Tests\Feature\Completion;

use App\Enums\AttendanceStatus;
use App\Enums\ClassSessionMode;
use App\Enums\ComponentKind;
use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Enums\ThreadVisibility;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\DiscussionBoard;
use App\Models\DiscussionGrade;
use App\Models\DiscussionThread;
use App\Models\Enrollment;
use App\Models\EnrollmentItemCompletion;
use App\Models\GradebookComponent;
use App\Models\User;
use App\Models\Week;
use App\Services\Live\AttendanceService;

trait CompletionFixtures
{
    protected function offering(string $code = 'CMP1'): CourseOffering
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
            'mode' => OfferingMode::Cohort,
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

    protected function admin(): User
    {
        return User::factory()->withRole(RoleType::AcademicAdmin)->create();
    }

    protected function instructorOn(CourseOffering $offering): User
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return $instructor;
    }

    protected function setGrade(CourseOffering $offering, User $student, float $percent): void
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

    protected function setAttendance(User $actor, CourseOffering $offering, User $student, int $present, int $total): void
    {
        $service = app(AttendanceService::class);

        for ($i = 0; $i < $total; $i++) {
            $session = $service->openSession($actor, $offering, [
                'title' => 'S'.$i,
                'scheduled_start' => now()->addHours($i + 1),
                'duration_minutes' => 60,
                'mode' => ClassSessionMode::InPerson->value,
            ]);
            $service->markRoster($actor, $session, [[
                'student_id' => $student->id,
                'status' => $i < $present ? AttendanceStatus::Present->value : AttendanceStatus::Absent->value,
            ]], 0);
        }
    }

    protected function requiredItem(CourseOffering $offering, Enrollment $enrollment, bool $complete): ContentItem
    {
        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week 1',
            'order' => 1,
        ]);

        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Reading,
            'title' => 'Read me',
            'order' => 1,
        ]);

        if ($complete) {
            EnrollmentItemCompletion::query()->create([
                'enrollment_id' => $enrollment->id,
                'content_item_id' => $item->id,
                'completed_at' => now(),
            ]);
        }

        return $item;
    }
}
