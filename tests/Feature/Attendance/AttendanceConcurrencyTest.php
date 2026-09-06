<?php

namespace Tests\Feature\Attendance;

require_once __DIR__.'/AttendanceFixtures.php';

use App\Enums\AttendanceStatus;
use App\Enums\RoleType;
use App\Exceptions\ConflictException;
use App\Models\AttendanceEntry;
use App\Models\User;
use App\Services\Live\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceConcurrencyTest extends TestCase
{
    use AttendanceFixtures;
    use RefreshDatabase;

    #[Test]
    public function a_stale_bulk_mark_returns_409_and_does_not_overwrite(): void
    {
        $offering = $this->offering('CAS1');
        $first = $this->instructorOn($offering);
        $second = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($second, $offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $session = $this->openSession($first, $offering);
        $service = app(AttendanceService::class);

        $service->markRoster($first, $session, [[
            'student_id' => $student->id,
            'status' => AttendanceStatus::Present->value,
        ]], 0);

        try {
            $service->markRoster($second, $session->fresh(), [[
                'student_id' => $student->id,
                'status' => AttendanceStatus::Absent->value,
            ]], 0);
            $this->fail('Expected stale lock_version to conflict');
        } catch (ConflictException $e) {
            $this->assertSame(__('attendance.stale_write'), $e->getMessage());
        }

        $entry = AttendanceEntry::query()->where('student_id', $student->id)->first();
        $this->assertSame(AttendanceStatus::Present, $entry->status);
        $this->assertSame(1, $session->fresh()->lock_version);
    }
}
