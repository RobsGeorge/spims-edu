<?php

namespace Tests\Feature\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CoursePrerequisite;
use App\Models\Enrollment;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\Semester;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Enrollment\DegreeAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnrollmentEngineTest extends TestCase
{
    use RefreshDatabase;

    private function openSemester(): Semester
    {
        $year = AcademicYear::query()->create([
            'name' => '2026/2027',
            'start_date' => now()->subMonths(1),
            'end_date' => now()->addMonths(8),
        ]);

        return Semester::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'Fall',
            'start_date' => now()->subWeeks(1),
            'end_date' => now()->addMonths(3),
            'registration_start' => now()->subDays(10),
            'registration_end' => now()->addDays(10),
            'add_drop_end_week' => 4,
            'last_withdrawal_week' => 10,
            'withdrawal_refund_percent' => 50,
        ]);
    }

    private function programBundle(User $student): array
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $program = Program::query()->create([
            'code' => 'DIP',
            'name' => 'Diploma',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 6,
            'max_courses_per_semester' => 2,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 3,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);

        $prereq = Course::query()->create(['code' => 'BASE', 'title' => 'Base', 'credit_hours' => 3, 'active' => true]);
        $course = Course::query()->create(['code' => 'ADV', 'title' => 'Advanced', 'credit_hours' => 3, 'active' => true]);
        CoursePrerequisite::query()->create([
            'course_id' => $course->id,
            'prerequisite_id' => $prereq->id,
        ]);

        ProgramCourse::query()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'requirement' => RequirementType::Required,
        ]);

        $sp = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        return compact('program', 'prereq', 'course', 'sp');
    }

    #[Test]
    public function enrollment_enforces_prerequisites_window_and_waitlist(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $bundle = $this->programBundle($student);
        $semester = $this->openSemester();

        $offering = CourseOffering::query()->create([
            'course_id' => $bundle['course']->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'seat_capacity' => 1,
            'status' => 'OPEN',
        ]);

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
            'student_program_id' => $bundle['sp']->id,
        ])->assertSessionHasErrors('enrollment');

        AcademicRecord::query()->create([
            'student_id' => $student->id,
            'course_id' => $bundle['prereq']->id,
            'letter_grade' => 'A',
            'percent' => 95,
            'gpa_points' => 4,
            'credit_hours' => 3,
            'term' => 'prior',
            'is_passing' => true,
            'completed_at' => now()->subYear(),
        ]);

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
            'student_program_id' => $bundle['sp']->id,
        ])->assertRedirect();

        $this->assertSame(EnrollmentStatus::Enrolled, Enrollment::query()->first()->status);

        $student2 = User::factory()->withRole(RoleType::Student)->create();
        StudentProgram::query()->create([
            'student_id' => $student2->id,
            'program_id' => $bundle['program']->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);
        AcademicRecord::query()->create([
            'student_id' => $student2->id,
            'course_id' => $bundle['prereq']->id,
            'letter_grade' => 'B',
            'percent' => 85,
            'gpa_points' => 3,
            'credit_hours' => 3,
            'term' => 'prior',
            'is_passing' => true,
            'completed_at' => now()->subYear(),
        ]);
        $sp2 = StudentProgram::query()->where('student_id', $student2->id)->first();

        $this->actingAs($student2)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
            'student_program_id' => $sp2->id,
        ])->assertRedirect();

        $this->assertSame(
            EnrollmentStatus::Waitlisted,
            Enrollment::query()->where('student_id', $student2->id)->first()->status
        );
    }

    #[Test]
    public function drop_promotes_waitlist_and_financial_hold_blocks(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $wait = User::factory()->withRole(RoleType::Student)->create();
        $bundle = $this->programBundle($student);
        $semester = $this->openSemester();

        foreach ([$student, $wait] as $u) {
            if ($u->id !== $student->id) {
                StudentProgram::query()->create([
                    'student_id' => $u->id,
                    'program_id' => $bundle['program']->id,
                    'status' => StudentProgramStatus::Active,
                    'enrolled_at' => now(),
                ]);
            }
            AcademicRecord::query()->create([
                'student_id' => $u->id,
                'course_id' => $bundle['prereq']->id,
                'letter_grade' => 'A',
                'percent' => 90,
                'gpa_points' => 4,
                'credit_hours' => 3,
                'term' => 'prior',
                'is_passing' => true,
                'completed_at' => now()->subYear(),
            ]);
        }

        $offering = CourseOffering::query()->create([
            'course_id' => $bundle['course']->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'seat_capacity' => 1,
            'status' => 'OPEN',
        ]);

        $this->actingAs($adm)->post(route('admin.enrollments.financial-hold', $student), ['held' => true]);
        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
            'student_program_id' => $bundle['sp']->id,
        ])->assertSessionHasErrors('enrollment');

        $this->actingAs($adm)->post(route('admin.enrollments.financial-hold', $student), ['held' => false]);
        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
            'student_program_id' => $bundle['sp']->id,
        ])->assertRedirect();

        $spWait = StudentProgram::query()->where('student_id', $wait->id)->first();
        $this->actingAs($wait)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
            'student_program_id' => $spWait->id,
        ])->assertRedirect();

        $enrollment = Enrollment::query()->where('student_id', $student->id)->first();
        $this->actingAs($student)->post(route('enrollments.drop', $enrollment))->assertRedirect();

        $this->assertSame(EnrollmentStatus::Dropped, $enrollment->fresh()->status);
        $this->assertSame(
            EnrollmentStatus::Enrolled,
            Enrollment::query()->where('student_id', $wait->id)->first()->status
        );
    }

    #[Test]
    public function self_paced_standalone_enrolls_anytime_and_degree_audit_works(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $course = Course::query()->create([
            'code' => 'FREE1',
            'title' => 'Standalone',
            'credit_hours' => 2,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
        ])->assertRedirect();

        $this->assertSame(1, Enrollment::query()->count());

        $bundle = $this->programBundle($student);
        $audit = app(DegreeAuditService::class)->audit($student, $bundle['sp']);
        $this->assertSame(0, $audit['required_met']);
        $this->assertSame(1, $audit['required_total']);
        $this->assertNotEmpty($audit['remaining']);
    }

    #[Test]
    public function admin_can_override_registration_rules(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $course = Course::query()->create([
            'code' => 'X',
            'title' => 'X',
            'credit_hours' => 1,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);

        $this->actingAs($adm)->post(route('admin.enrollments.financial-hold', $student), ['held' => true]);

        $this->actingAs($adm)->post(route('admin.enrollments.override'), [
            'student_id' => $student->id,
            'offering_id' => $offering->id,
        ])->assertRedirect();

        $this->assertSame(1, Enrollment::query()->where('student_id', $student->id)->count());
    }
}
