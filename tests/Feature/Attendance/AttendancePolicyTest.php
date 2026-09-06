<?php

namespace Tests\Feature\Attendance;

require_once __DIR__.'/AttendanceFixtures.php';

use App\Enums\AttendanceStatus;
use App\Enums\RoleType;
use App\Models\User;
use App\Services\Live\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendancePolicyTest extends TestCase
{
    use AttendanceFixtures;
    use RefreshDatabase;

    #[Test]
    public function late_weight_and_min_percentage_change_computed_percent(): void
    {
        $offering = $this->offering('POL1');
        $instructor = $this->instructorOn($offering);
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $service = app(AttendanceService::class);

        $first = $this->openSession($instructor, $offering, ['title' => 'A']);
        $second = $this->openSession($instructor, $offering, ['title' => 'B']);
        $service->markRoster($instructor, $first, [[
            'student_id' => $student->id,
            'status' => AttendanceStatus::Present->value,
        ]], 0);
        $service->markRoster($instructor, $second, [[
            'student_id' => $student->id,
            'status' => AttendanceStatus::Late->value,
        ]], 0);

        $this->assertSame(50.0, $service->percentFor($student, $offering));

        $service->savePolicy($admin, [
            'offering_id' => $offering->id,
            'min_percentage' => 0,
            'late_grade_percentage' => 50,
            'counts_toward_grade' => true,
            'is_enabled' => true,
        ]);
        $this->assertSame(75.0, $service->percentFor($student, $offering));

        $service->savePolicy($admin, [
            'offering_id' => $offering->id,
            'min_percentage' => 80,
            'late_grade_percentage' => 50,
            'counts_toward_grade' => true,
            'is_enabled' => true,
        ]);
        $this->assertSame(0.0, $service->percentFor($student, $offering));
    }

    #[Test]
    public function a_disabled_policy_contributes_nothing(): void
    {
        $offering = $this->offering('POL2');
        $instructor = $this->instructorOn($offering);
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $service = app(AttendanceService::class);
        $session = $this->openSession($instructor, $offering);
        $service->markRoster($instructor, $session, [[
            'student_id' => $student->id,
            'status' => AttendanceStatus::Present->value,
        ]], 0);

        $service->savePolicy($admin, [
            'offering_id' => $offering->id,
            'min_percentage' => 75,
            'late_grade_percentage' => 50,
            'counts_toward_grade' => true,
            'is_enabled' => false,
        ]);

        $this->assertNull($service->percentFor($student, $offering));
    }
}
