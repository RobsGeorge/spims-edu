<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Services\Live\AttendanceService;
use PHPUnit\Framework\Attributes\Test;

class AttendanceScopeTest extends AttendanceTestCase
{
    #[Test]
    public function an_instructor_cannot_mark_another_offerings_roster(): void
    {
        $mine = $this->offering('SCP1');
        $theirs = $this->offering('SCP2');
        $instructor = $this->instructorOn($mine);
        $theirInstructor = $this->instructorOn($theirs);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $theirs);
        $session = $this->openSession($theirInstructor, $theirs);

        $this->expectException(AuthorizationException::class);
        app(AttendanceService::class)->markRoster($instructor, $session, [[
            'student_id' => $student->id,
            'status' => AttendanceStatus::Present->value,
        ]], 0);
    }

    #[Test]
    public function an_instructor_cannot_open_a_session_on_another_offering(): void
    {
        $mine = $this->offering('SCP3');
        $theirs = $this->offering('SCP4');
        $instructor = $this->instructorOn($mine);

        $this->expectException(AuthorizationException::class);
        $this->openSession($instructor, $theirs);
    }
}
