<?php

namespace Tests\Feature\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
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
use App\Services\Gradebook\GradebookService;
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

    #[Test]
    public function credit_and_course_caps_count_only_the_target_semester(): void
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $program = Program::query()->create([
            'code' => 'CAP',
            'name' => 'Caps',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 3,
            'max_courses_per_semester' => 1,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 0,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);

        $courses = [];
        foreach (['CA1', 'CA2', 'CA3', 'CA4', 'CA5'] as $code) {
            $course = Course::query()->create([
                'code' => $code,
                'title' => $code,
                'credit_hours' => 3,
                'active' => true,
            ]);
            ProgramCourse::query()->create([
                'program_id' => $program->id,
                'course_id' => $course->id,
                'requirement' => RequirementType::Required,
            ]);
            $courses[] = $course;
        }

        $sp = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        $year = AcademicYear::query()->create([
            'name' => '2025/2026',
            'start_date' => now()->subYear(),
            'end_date' => now()->addMonths(2),
        ]);
        $prior = Semester::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'Spring',
            'start_date' => now()->subMonths(8),
            'end_date' => now()->subMonths(4),
            'registration_start' => now()->subMonths(9),
            'registration_end' => now()->subMonths(7),
            'add_drop_end_week' => 4,
            'last_withdrawal_week' => 10,
            'withdrawal_refund_percent' => 50,
        ]);
        $current = $this->openSemester();

        foreach ([$courses[0], $courses[1]] as $course) {
            $priorOffering = CourseOffering::query()->create([
                'course_id' => $course->id,
                'semester_id' => $prior->id,
                'mode' => OfferingMode::Cohort,
                'status' => 'OPEN',
            ]);
            Enrollment::query()->create([
                'student_id' => $student->id,
                'offering_id' => $priorOffering->id,
                'student_program_id' => $sp->id,
                'status' => EnrollmentStatus::Enrolled,
                'enrolled_at' => now()->subMonths(6),
            ]);
        }

        $pastSelfPaced = CourseOffering::query()->create([
            'course_id' => $courses[2]->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
            'start_date' => now()->subYears(2),
            'end_date' => now()->subYear(),
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $pastSelfPaced->id,
            'student_program_id' => $sp->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now()->subYears(2),
        ]);

        $firstCurrent = CourseOffering::query()->create([
            'course_id' => $courses[3]->id,
            'semester_id' => $current->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $firstCurrent->id,
            'student_program_id' => $sp->id,
        ])->assertRedirect();

        $secondCurrent = CourseOffering::query()->create([
            'course_id' => $courses[4]->id,
            'semester_id' => $current->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $secondCurrent->id,
            'student_program_id' => $sp->id,
        ])->assertSessionHasErrors('enrollment');
    }

    #[Test]
    public function overlapping_self_paced_offerings_count_against_current_term_caps(): void
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $program = Program::query()->create([
            'code' => 'SPX',
            'name' => 'Self-paced caps',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 3,
            'max_courses_per_semester' => 1,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 0,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);

        $selfPacedCourse = Course::query()->create([
            'code' => 'SP1',
            'title' => 'Self one',
            'credit_hours' => 3,
            'active' => true,
        ]);
        $termCourse = Course::query()->create([
            'code' => 'TM1',
            'title' => 'Term one',
            'credit_hours' => 3,
            'active' => true,
        ]);
        foreach ([$selfPacedCourse, $termCourse] as $course) {
            ProgramCourse::query()->create([
                'program_id' => $program->id,
                'course_id' => $course->id,
                'requirement' => RequirementType::Required,
            ]);
        }

        $sp = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        $current = $this->openSemester();
        $selfPaced = CourseOffering::query()->create([
            'course_id' => $selfPacedCourse->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
            'start_date' => now()->subWeek(),
            'end_date' => now()->addMonths(2),
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $selfPaced->id,
            'student_program_id' => $sp->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now()->subDays(2),
        ]);

        $termOffering = CourseOffering::query()->create([
            'course_id' => $termCourse->id,
            'semester_id' => $current->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $termOffering->id,
            'student_program_id' => $sp->id,
        ])->assertSessionHasErrors('enrollment');
    }

    #[Test]
    public function locking_a_passing_grade_marks_the_enrollment_completed(): void
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $failingStudent = User::factory()->withRole(RoleType::Student)->create();
        $course = Course::query()->create([
            'code' => 'LOCK1',
            'title' => 'Lock course',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        $this->staffOffering($instructor, $offering);

        $passing = Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'final_percent' => 95,
            'final_letter' => 'A',
            'final_gpa_points' => 4,
            'grade_status' => GradeStatus::Submitted,
            'grade_type' => GradeType::Standard,
        ]);
        $failing = Enrollment::query()->create([
            'student_id' => $failingStudent->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'final_percent' => 40,
            'final_letter' => 'F',
            'final_gpa_points' => 0,
            'grade_status' => GradeStatus::Submitted,
            'grade_type' => GradeType::Standard,
        ]);

        app(GradebookService::class)->lockGrades($instructor, $offering);

        $this->assertSame(EnrollmentStatus::Completed, $passing->fresh()->status);
        $this->assertSame(EnrollmentStatus::Enrolled, $failing->fresh()->status);
    }
}
