<?php

namespace Tests\Feature\Portal;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\LiveSession;
use App\Models\User;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeachLiveAttendanceImportTest extends TestCase
{
    use RefreshDatabase;

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
            'status' => OfferingStatus::Open,
            'attendance_threshold_percent' => 60,
        ]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return [$offering->fresh('course'), $instructor];
    }

    private function enroll(User $student, CourseOffering $offering): Enrollment
    {
        return Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);
    }

    private function liveSession(CourseOffering $offering, string $title): LiveSession
    {
        return LiveSession::query()->create([
            'offering_id' => $offering->id,
            'title' => $title,
            'scheduled_start' => now()->addHour(),
            'duration_minutes' => 100,
            'zoom_meeting_id' => 'm-'.$title,
            'zoom_join_url' => 'https://zoom.test/j/1',
        ]);
    }

    #[Test]
    public function staffed_instructor_lists_sessions_and_imports_from_json(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $instructor] = $this->staffedOffering('TLZ1');
        $student = User::factory()->withRole(RoleType::Student)->create(['email' => 'zoom-stu@example.com']);
        $this->enroll($student, $offering);
        $session = $this->liveSession($offering, 'Lecture 1');

        $this->actingAs($instructor)
            ->get(route('teach.live.index', $offering))
            ->assertOk()
            ->assertSee('Lecture 1', false)
            ->assertSee(__('live.import_attendance'), false)
            ->assertSee(route('teach.live.attendance.import', [$offering, $session]), false);

        $this->assertSame(0, AttendanceRecord::query()->count());

        $this->actingAs($instructor)
            ->from(route('teach.live.index', $offering))
            ->post(route('teach.live.attendance.import', [$offering, $session]), [
                'participants_json' => json_encode([
                    ['email' => 'zoom-stu@example.com', 'minutes' => 80],
                ]),
            ])
            ->assertRedirect(route('teach.live.index', $offering))
            ->assertSessionHas('status', __('live.attendance_imported', ['count' => 1]));

        $this->assertSame(1, AttendanceRecord::query()->count());
        $this->assertSame($student->id, AttendanceRecord::query()->first()->student_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance.import',
            'actor_id' => $instructor->id,
            'entity_type' => 'LiveSession',
            'entity_id' => $session->id,
        ]);
    }

    #[Test]
    public function staffed_instructor_imports_from_row_fields(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $instructor] = $this->staffedOffering('TLZ2');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $session = $this->liveSession($offering, 'Lab');

        $this->actingAs($instructor)
            ->from(route('teach.live.index', $offering))
            ->post(route('teach.live.attendance.import', [$offering, $session]), [
                'participants' => [
                    ['user_id' => $student->id, 'minutes' => 90],
                ],
            ])
            ->assertRedirect(route('teach.live.index', $offering))
            ->assertSessionHas('status', __('live.attendance_imported', ['count' => 1]));

        $this->assertSame($student->id, AttendanceRecord::query()->first()->student_id);
    }

    #[Test]
    public function cross_offering_instructor_is_forbidden(): void
    {
        $this->seed(ThemeSeeder::class);
        [$mine, $instructorA] = $this->staffedOffering('TLZ3A');
        [, $instructorB] = $this->staffedOffering('TLZ3B');
        $session = $this->liveSession($mine, 'A only');

        $this->actingAs($instructorB)
            ->get(route('teach.live.index', $mine))
            ->assertForbidden();

        $this->actingAs($instructorB)
            ->post(route('teach.live.attendance.import', [$mine, $session]), [
                'participants' => [['user_id' => $instructorA->id, 'minutes' => 10]],
            ])
            ->assertForbidden();
    }

    #[Test]
    public function import_requires_participants(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $instructor] = $this->staffedOffering('TLZ4');
        $session = $this->liveSession($offering, 'Empty');

        $this->actingAs($instructor)
            ->from(route('teach.live.index', $offering))
            ->post(route('teach.live.attendance.import', [$offering, $session]), [])
            ->assertRedirect(route('teach.live.index', $offering))
            ->assertSessionHasErrors('participants');

        $this->actingAs($instructor)
            ->from(route('teach.live.index', $offering))
            ->post(route('teach.live.attendance.import', [$offering, $session]), [
                'participants_json' => 'not-json',
            ])
            ->assertRedirect(route('teach.live.index', $offering))
            ->assertSessionHasErrors('participants_json');

        $this->assertSame(0, AttendanceRecord::query()->count());
    }
}
