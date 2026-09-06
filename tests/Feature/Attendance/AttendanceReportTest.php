<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\RoleType;
use App\Models\User;
use App\Services\Live\AttendanceService;
use PHPUnit\Framework\Attributes\Test;

class AttendanceReportTest extends AttendanceTestCase
{
    #[Test]
    public function report_includes_per_student_per_session_and_aggregate_and_csv(): void
    {
        $offering = $this->offering('RPT1');
        $instructor = $this->instructorOn($offering);
        $a = User::factory()->withRole(RoleType::Student)->create(['first_name' => 'Ada']);
        $b = User::factory()->withRole(RoleType::Student)->create(['first_name' => 'Ben']);
        $this->enroll($a, $offering);
        $this->enroll($b, $offering);
        $service = app(AttendanceService::class);
        $session = $this->openSession($instructor, $offering);

        $service->markRoster($instructor, $session, [
            ['student_id' => $a->id, 'status' => AttendanceStatus::Present->value],
            ['student_id' => $b->id, 'status' => AttendanceStatus::Absent->value],
        ], 0);

        $report = $service->report($instructor, $offering);
        $this->assertCount(2, $report['students']);
        $this->assertCount(1, $report['sessions']);
        $this->assertSame(1, $report['aggregate']['session_count']);
        $this->assertSame(2, $report['aggregate']['student_count']);
        $this->assertSame(1, $report['aggregate']['present']);
        $this->assertSame(1, $report['aggregate']['absent']);

        $this->actingAs($instructor)
            ->get(route('teach.attendance.report.csv', $offering))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('student_id,first_name,last_name,email,present,absent,late,excused,percent')
            ->assertSee('Ada')
            ->assertSee('Ben');
    }
}
