<?php

namespace Tests\Feature\Attendance;

use App\Enums\ClassSessionMode;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Live\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AttendanceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function offering(string $code = 'ATT1'): CourseOffering
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

    protected function instructorOn(CourseOffering $offering): User
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return $instructor;
    }

    protected function taOn(CourseOffering $offering): User
    {
        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $this->staffOffering($ta, $offering, \App\Enums\OfferingStaffRole::Ta);

        return $ta;
    }

    protected function openSession(User $actor, CourseOffering $offering, array $overrides = []): ClassSession
    {
        return app(AttendanceService::class)->openSession($actor, $offering, array_merge([
            'title' => 'Lecture',
            'scheduled_start' => now()->addHour(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ], $overrides));
    }

    protected function apiToken(User $user, string $ability): string
    {
        return $user->createToken('test', [$ability])->plainTextToken;
    }
}
