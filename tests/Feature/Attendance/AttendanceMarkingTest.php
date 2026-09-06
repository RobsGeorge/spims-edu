<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ResourceLockedException;
use App\Models\AttendanceEntry;
use App\Models\User;
use App\Services\Live\AttendanceService;
use PHPUnit\Framework\Attributes\Test;

class AttendanceMarkingTest extends AttendanceTestCase
{
    #[Test]
    public function instructor_can_mark_amend_excuse_fill_missing_and_close(): void
    {
        $offering = $this->offering();
        $instructor = $this->instructorOn($offering);
        $present = User::factory()->withRole(RoleType::Student)->create();
        $missing = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($present, $offering);
        $this->enroll($missing, $offering);
        $session = $this->openSession($instructor, $offering);
        $service = app(AttendanceService::class);

        $service->markRoster($instructor, $session, [[
            'student_id' => $present->id,
            'status' => AttendanceStatus::Present->value,
        ]], 0);

        $this->assertSame(AttendanceStatus::Present, AttendanceEntry::query()->where('student_id', $present->id)->first()->status);

        $session->refresh();
        $service->markRoster($instructor, $session, [[
            'student_id' => $present->id,
            'status' => AttendanceStatus::Late->value,
        ]], $session->lock_version);

        $this->assertSame(AttendanceStatus::Late, AttendanceEntry::query()->where('student_id', $present->id)->first()->fresh()->status);

        $session->refresh();
        $service->excuse($instructor, $session, $present, 'Illness', $session->lock_version);
        $this->assertSame(AttendanceStatus::Excused, AttendanceEntry::query()->where('student_id', $present->id)->first()->fresh()->status);

        $filled = $service->fillMissing($instructor, $session);
        $this->assertCount(1, $filled);
        $this->assertSame(AttendanceStatus::Absent, AttendanceEntry::query()->where('student_id', $missing->id)->first()->status);

        $service->closeSession($instructor, $session->fresh());
        $this->assertTrue($session->fresh()->isClosed());

        $this->expectException(ResourceLockedException::class);
        $service->markRoster($instructor, $session->fresh(), [[
            'student_id' => $present->id,
            'status' => AttendanceStatus::Present->value,
        ]], $session->fresh()->lock_version);
    }

    #[Test]
    public function reopen_requires_attendance_reopen(): void
    {
        $offering = $this->offering('REOP');
        $instructor = $this->instructorOn($offering);
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $session = $this->openSession($instructor, $offering);
        app(AttendanceService::class)->closeSession($instructor, $session);

        try {
            app(AttendanceService::class)->reopenSession($instructor, $session->fresh());
            $this->fail('Instructor should not reopen');
        } catch (AuthorizationException) {
            $this->assertTrue($session->fresh()->isClosed());
        }

        $reopened = app(AttendanceService::class)->reopenSession($admin, $session->fresh());
        $this->assertFalse($reopened->isClosed());
    }
}
