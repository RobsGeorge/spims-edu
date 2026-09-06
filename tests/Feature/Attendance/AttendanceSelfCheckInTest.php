<?php

namespace Tests\Feature\Attendance;

require_once __DIR__.'/AttendanceFixtures.php';

use App\Enums\AttendanceStatus;
use App\Enums\RoleType;
use App\Models\AttendanceEntry;
use App\Models\User;
use App\Services\Live\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceSelfCheckInTest extends TestCase
{
    use AttendanceFixtures;
    use RefreshDatabase;

    #[Test]
    public function a_valid_code_marks_the_enrolled_student_present(): void
    {
        $offering = $this->offering('CHK1');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $session = $this->openSession($instructor, $offering);
        $code = app(AttendanceService::class)->issueCheckInCode($instructor, $session);

        $entry = app(AttendanceService::class)->selfCheckIn($student, $session, $code->code);

        $this->assertSame(AttendanceStatus::Present, $entry->status);
        $this->assertSame(1, $code->fresh()->uses);
    }

    #[Test]
    public function expired_over_limit_wrong_session_replayed_and_unenrolled_codes_fail(): void
    {
        $offering = $this->offering('CHK2');
        $other = $this->offering('CHK3');
        $instructor = $this->instructorOn($offering);
        $this->staffOffering($instructor, $other);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $stranger = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $session = $this->openSession($instructor, $offering);
        $otherSession = $this->openSession($instructor, $other, ['title' => 'Other']);
        $service = app(AttendanceService::class);

        $expired = $service->issueCheckInCode($instructor, $session, ['ttl_minutes' => 5]);
        $expired->update(['expires_at' => now()->subMinute()]);
        $this->assertCheckInFails($student, $session, $expired->code, 'attendance.check_in_expired');

        $limited = $service->issueCheckInCode($instructor, $session, ['max_uses' => 1]);
        $limited->update(['uses' => 1]);
        $this->assertCheckInFails($student, $session, $limited->code, 'attendance.check_in_exhausted');

        $foreign = $service->issueCheckInCode($instructor, $otherSession);
        $this->assertCheckInFails($student, $session, $foreign->code, 'attendance.check_in_wrong_session');

        $ok = $service->issueCheckInCode($instructor, $session);
        $service->selfCheckIn($student, $session, $ok->code);
        $this->assertCheckInFails($student, $session, $ok->code, 'attendance.check_in_replayed');

        $this->assertCheckInFails($stranger, $session, $ok->code, 'attendance.student_not_enrolled');
        $this->assertSame(1, AttendanceEntry::query()->where('student_id', $student->id)->count());
    }

    private function assertCheckInFails(User $student, $session, string $code, string $key): void
    {
        try {
            app(AttendanceService::class)->selfCheckIn($student, $session, $code);
            $this->fail("Expected $key");
        } catch (ValidationException $e) {
            $this->assertSame([__(''.$key)], $e->errors()['code']);
        }
    }
}
