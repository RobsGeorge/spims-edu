<?php

namespace Tests\Feature\Api;

use App\Enums\AttendanceStatus;
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
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function offering(string $code): CourseOffering
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
        ]);
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

    private function token(User $user, string $role): string
    {
        return $user->createToken('api', ["role:{$role}"])->plainTextToken;
    }

    #[Test]
    public function a_student_sees_only_their_own_attendance_history(): void
    {
        $offering = $this->offering('API1');
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);
        $mine = User::factory()->withRole(RoleType::Student)->create();
        $peer = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($mine, $offering);
        $this->enroll($peer, $offering);
        $session = app(AttendanceService::class)->openSession($instructor, $offering, [
            'title' => 'API lecture',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);
        app(AttendanceService::class)->markRoster($instructor, $session, [
            ['student_id' => $mine->id, 'status' => AttendanceStatus::Present->value],
            ['student_id' => $peer->id, 'status' => AttendanceStatus::Absent->value],
        ], 0);

        $response = $this->withToken($this->token($mine, 'STUDENT'))
            ->getJson(route('api.v1.attendance.mine'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('status');
        $this->assertTrue($ids->contains(AttendanceStatus::Present->value));
        $this->assertFalse($ids->contains(AttendanceStatus::Absent->value));
        $this->assertSame(1, $response->json('meta.total'));
    }

    #[Test]
    public function instructor_bulk_mark_returns_409_on_stale_lock_version(): void
    {
        $offering = $this->offering('API2');
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $session = app(AttendanceService::class)->openSession($instructor, $offering, [
            'title' => 'CAS',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);

        $token = $this->token($instructor, 'INSTRUCTOR');
        $this->withToken($token)
            ->postJson(route('api.v1.teach.sessions.attendance', $session), [
                'lock_version' => 0,
                'marks' => [['student_id' => $student->id, 'status' => 'PRESENT']],
            ])
            ->assertOk()
            ->assertJsonPath('data.lock_version', 1);

        Auth::forgetGuards();

        $this->withToken($token)
            ->postJson(route('api.v1.teach.sessions.attendance', $session), [
                'lock_version' => 0,
                'marks' => [['student_id' => $student->id, 'status' => 'ABSENT']],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    }

    #[Test]
    public function a_ta_can_mark_their_offering_but_not_another(): void
    {
        $mine = $this->offering('API3');
        $theirs = $this->offering('API4');
        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $this->staffOffering($ta, $mine);
        $otherIns = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($otherIns, $theirs);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $mine);
        $foreign = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($foreign, $theirs);

        $mineSession = app(AttendanceService::class)->openSession(
            User::factory()->withRole(RoleType::Instructor)->create()->tap(fn ($u) => $this->staffOffering($u, $mine)),
            $mine,
            ['title' => 'Mine', 'scheduled_start' => now(), 'duration_minutes' => 60, 'mode' => ClassSessionMode::InPerson->value]
        );
        $theirSession = app(AttendanceService::class)->openSession($otherIns, $theirs, [
            'title' => 'Theirs',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);

        $token = $this->token($ta, 'TA');
        $this->withToken($token)
            ->postJson(route('api.v1.teach.sessions.attendance', $mineSession), [
                'lock_version' => 0,
                'marks' => [['student_id' => $student->id, 'status' => 'PRESENT']],
            ])
            ->assertOk();

        Auth::forgetGuards();

        $this->withToken($token)
            ->postJson(route('api.v1.teach.sessions.attendance', $theirSession), [
                'lock_version' => 0,
                'marks' => [['student_id' => $foreign->id, 'status' => 'PRESENT']],
            ])
            ->assertForbidden();
    }

    #[Test]
    public function check_in_succeeds_and_rejects_bad_codes(): void
    {
        $offering = $this->offering('API5');
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $session = app(AttendanceService::class)->openSession($instructor, $offering, [
            'title' => 'Check',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);
        $code = app(AttendanceService::class)->issueCheckInCode($instructor, $session);

        $token = $this->token($student, 'STUDENT');
        $this->withToken($token)
            ->postJson(route('api.v1.sessions.check-in', $session), ['code' => $code->code])
            ->assertCreated()
            ->assertJsonPath('data.status', 'PRESENT');

        Auth::forgetGuards();

        $this->withToken($token)
            ->postJson(route('api.v1.sessions.check-in', $session), ['code' => $code->code])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        Auth::forgetGuards();

        $this->withToken($token)
            ->postJson(route('api.v1.sessions.check-in', $session), ['code' => 'NOPE'])
            ->assertStatus(422);
    }
}
