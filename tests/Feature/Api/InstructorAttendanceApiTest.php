<?php

namespace Tests\Feature\Api;

use App\Enums\AttendanceStatus;
use App\Enums\ClassSessionMode;
use App\Enums\RoleType;
use App\Models\User;
use App\Services\Live\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstructorAttendanceApiTest extends TestCase
{
    use InstructorApiFixtures;
    use RefreshDatabase;

    #[Test]
    public function bulk_mark_rejects_stale_lock_version_with_409(): void
    {
        $offering = $this->offering('S8ATT1');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $session = app(AttendanceService::class)->openSession($instructor, $offering, [
            'title' => 'Lock',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/sessions/'.$session->id.'/attendance', [
                'lock_version' => 0,
                'marks' => [['student_id' => $student->id, 'status' => AttendanceStatus::Present->value]],
            ])
            ->assertOk()
            ->assertJsonPath('data.lock_version', 1);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/sessions/'.$session->id.'/attendance', [
                'lock_version' => 0,
                'marks' => [['student_id' => $student->id, 'status' => AttendanceStatus::Absent->value]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    }

    #[Test]
    public function fill_missing_then_close_locks_further_writes_with_423(): void
    {
        $offering = $this->offering('S8ATT2');
        $instructor = $this->instructorOn($offering);
        $present = User::factory()->withRole(RoleType::Student)->create();
        $missing = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($present, $offering);
        $this->enroll($missing, $offering);
        $session = app(AttendanceService::class)->openSession($instructor, $offering, [
            'title' => 'Fill',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/sessions/'.$session->id.'/attendance', [
                'lock_version' => 0,
                'marks' => [['student_id' => $present->id, 'status' => AttendanceStatus::Present->value]],
            ])
            ->assertOk()
            ->assertJsonPath('data.lock_version', 1);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/sessions/'.$session->id.'/attendance/fill-missing')
            ->assertOk()
            ->assertJsonPath('data.filled', 1);

        $closed = $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/sessions/'.$session->id.'/close')
            ->assertOk();
        $this->assertNotEmpty($closed->json('data.attendance_closed_at'));

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/sessions/'.$session->id.'/attendance', [
                'lock_version' => (int) $session->fresh()->lock_version,
                'marks' => [['student_id' => $present->id, 'status' => AttendanceStatus::Absent->value]],
            ])
            ->assertStatus(423)
            ->assertJsonPath('code', 'LOCKED');

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/sessions/'.$session->id.'/attendance/fill-missing')
            ->assertStatus(423)
            ->assertJsonPath('code', 'LOCKED');
    }
}
