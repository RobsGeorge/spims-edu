<?php

namespace Tests\Feature\Api;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\User;
use App\Support\AuthorizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstructorOpsLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizeService::class)->forgetMatrixCache();
    }

    #[Test]
    public function instructor_lists_live_sessions_with_join_url(): void
    {
        [$offering, $instructor] = $this->staffedOffering('LS1');
        $session = $this->liveSession($offering, 'Lecture 1', 'https://zoom.test/j/abc');

        $this->asInstructor($instructor)
            ->getJson(route('api.v1.teach.offerings.live-sessions', $offering))
            ->assertOk()
            ->assertJsonPath('data.0.id', $session->id)
            ->assertJsonPath('data.0.join_url', 'https://zoom.test/j/abc')
            ->assertJsonPath('data.0.title', 'Lecture 1');
    }

    #[Test]
    public function import_attendance_increments_records(): void
    {
        [$offering, $instructor] = $this->staffedOffering('LS2');
        $student = User::factory()->withRole(RoleType::Student)->create(['email' => 'zoom-stu@example.com']);
        $this->enroll($student, $offering);
        $session = $this->liveSession($offering, 'Lab');

        $this->assertSame(0, AttendanceRecord::query()->count());

        $this->asInstructor($instructor)
            ->postJson(route('api.v1.teach.live-sessions.attendance.import', $session), [
                'participants' => [
                    ['email' => 'zoom-stu@example.com', 'minutes' => 80],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $this->assertSame(1, AttendanceRecord::query()->count());
        $this->assertSame($student->id, AttendanceRecord::query()->first()->student_id);
    }

    #[Test]
    public function instructor_b_is_denied_live_ops_on_another_offering(): void
    {
        [$mine, $instructorA] = $this->staffedOffering('LS3A');
        [$theirs, $instructorB] = $this->staffedOffering('LS3B');
        $session = $this->liveSession($mine, 'A only');

        $this->asInstructor($instructorB)
            ->getJson(route('api.v1.teach.offerings.live-sessions', $mine))
            ->assertForbidden();

        $this->asInstructor($instructorB)
            ->postJson(route('api.v1.teach.live-sessions.attendance.import', $session), [
                'participants' => [['user_id' => $instructorA->id, 'minutes' => 10]],
            ])
            ->assertForbidden();

        $this->asInstructor($instructorA)
            ->getJson(route('api.v1.teach.offerings.live-sessions', $theirs))
            ->assertForbidden();
    }

    /**
     * @return array{0: CourseOffering, 1: User}
     */
    private function staffedOffering(string $code): array
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => "Course $code",
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
            'attendance_threshold_percent' => 60,
        ]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return [$offering, $instructor];
    }

    private function enroll(User $student, CourseOffering $offering): void
    {
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);
    }

    private function liveSession(CourseOffering $offering, string $title, ?string $joinUrl = 'https://zoom.test/j/1'): LiveSession
    {
        return LiveSession::query()->create([
            'offering_id' => $offering->id,
            'title' => $title,
            'scheduled_start' => now()->addHour(),
            'duration_minutes' => 100,
            'zoom_meeting_id' => 'm-'.$title,
            'zoom_join_url' => $joinUrl,
        ]);
    }

    private function asInstructor(User $user)
    {
        Auth::forgetGuards();

        return $this->withToken($user->createToken('api', ['role:INSTRUCTOR'])->plainTextToken);
    }
}
