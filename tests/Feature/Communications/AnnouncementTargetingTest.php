<?php

namespace Tests\Feature\Communications;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\ProgramType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicYear;
use App\Models\AnnouncementDelivery;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Communications\AnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnnouncementTargetingTest extends TestCase
{
    use RefreshDatabase;

    private function offering(string $code, ?string $semesterId = null): CourseOffering
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => $code,
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        return CourseOffering::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semesterId,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
    }

    #[Test]
    public function offering_program_role_and_explicit_user_audiences_resolve_and_outsiders_get_nothing(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $inOffering = User::factory()->withRole(RoleType::Student)->create();
        $inProgram = User::factory()->withRole(RoleType::Student)->create();
        $outsider = User::factory()->withRole(RoleType::Student)->create();
        $explicit = User::factory()->withRole(RoleType::Student)->create();
        $academic = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $offering = $this->offering('TGT1');
        $this->staffOffering($instructor, $offering);
        Enrollment::query()->create([
            'student_id' => $inOffering->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $program = Program::query()->create([
            'code' => 'TPROG',
            'name' => 'Target Program',
            'type' => ProgramType::Certificate,
            'max_credits_per_semester' => 6,
            'max_courses_per_semester' => 2,
            'max_semesters_to_graduate' => 4,
            'active' => true,
        ]);
        StudentProgram::query()->create([
            'student_id' => $inProgram->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        $year = AcademicYear::query()->create([
            'name' => '2026',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(8),
        ]);
        $semester = Semester::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'Fall',
            'start_date' => now(),
            'end_date' => now()->addMonths(4),
            'registration_start' => now()->subWeek(),
            'registration_end' => now()->addWeek(),
            'add_drop_end_week' => 2,
            'last_withdrawal_week' => 8,
            'status' => 'OPEN',
        ]);
        $semOffering = $this->offering('TGT2', $semester->id);
        $inSemester = User::factory()->withRole(RoleType::Student)->create();
        Enrollment::query()->create([
            'student_id' => $inSemester->id,
            'offering_id' => $semOffering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $service = app(AnnouncementService::class);

        $byOffering = $service->draft($instructor, $offering, [
            'title' => 'Offering',
            'body' => 'O',
            'targets' => [['type' => AnnouncementTargetType::Offering->value, 'id' => $offering->id]],
        ]);
        $service->publish($instructor, $byOffering);
        $this->assertTrue($this->deliveredTo($byOffering->id, $inOffering->id));
        $this->assertFalse($this->deliveredTo($byOffering->id, $outsider->id));

        $byProgram = $service->draft($instructor, $offering, [
            'title' => 'Program',
            'body' => 'P',
            'targets' => [['type' => AnnouncementTargetType::Program->value, 'id' => $program->id]],
        ]);
        $service->publish($instructor, $byProgram);
        $this->assertTrue($this->deliveredTo($byProgram->id, $inProgram->id));
        $this->assertFalse($this->deliveredTo($byProgram->id, $outsider->id));

        $byRole = $service->draft($instructor, $offering, [
            'title' => 'Role',
            'body' => 'R',
            'targets' => [['type' => AnnouncementTargetType::Role->value, 'id' => RoleType::AcademicAdmin->value]],
        ]);
        $service->publish($instructor, $byRole);
        $this->assertTrue($this->deliveredTo($byRole->id, $academic->id));
        $this->assertFalse($this->deliveredTo($byRole->id, $outsider->id));

        $byUser = $service->draft($instructor, $offering, [
            'title' => 'User',
            'body' => 'U',
            'targets' => [['type' => AnnouncementTargetType::User->value, 'id' => $explicit->id]],
        ]);
        $service->publish($instructor, $byUser);
        $this->assertTrue($this->deliveredTo($byUser->id, $explicit->id));
        $this->assertFalse($this->deliveredTo($byUser->id, $outsider->id));

        $bySemester = $service->draft($instructor, $offering, [
            'title' => 'Semester',
            'body' => 'S',
            'targets' => [['type' => AnnouncementTargetType::Semester->value, 'id' => $semester->id]],
        ]);
        $service->publish($instructor, $bySemester);
        $this->assertTrue($this->deliveredTo($bySemester->id, $inSemester->id));
        $this->assertFalse($this->deliveredTo($bySemester->id, $outsider->id));
    }

    private function deliveredTo(string $announcementId, string $userId): bool
    {
        return AnnouncementDelivery::query()
            ->where('announcement_id', $announcementId)
            ->where('recipient_id', $userId)
            ->exists();
    }
}
