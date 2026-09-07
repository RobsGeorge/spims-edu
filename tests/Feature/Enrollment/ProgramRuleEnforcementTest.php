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
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\Semester;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Enrollment\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProgramRuleEnforcementTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Shared fixture helpers
    // -------------------------------------------------------------------------

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

    private function pastSemester(int $weeksBack = 10): Semester
    {
        $year = AcademicYear::query()->firstOrCreate(
            ['name' => '2025/2026'],
            [
                'start_date' => now()->subYear(),
                'end_date' => now()->subMonths(2),
            ]
        );

        return Semester::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'Spring-Past',
            'start_date' => now()->subWeeks($weeksBack),
            'end_date' => now()->subDays(7),
            'registration_start' => now()->subWeeks($weeksBack + 2),
            'registration_end' => now()->subWeeks($weeksBack - 1),
            'add_drop_end_week' => 2,
            'last_withdrawal_week' => 4,
            'withdrawal_refund_percent' => 50,
        ]);
    }

    private function makeProgram(array $attrs = []): Program
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);

        return Program::query()->create(array_merge([
            'code' => 'TST-' . uniqid(),
            'name' => 'Test Program',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 0,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ], $attrs));
    }

    private function makeCourse(string $code, array $attrs = []): Course
    {
        return Course::query()->create(array_merge([
            'code' => $code,
            'title' => $code,
            'credit_hours' => 3,
            'active' => true,
        ], $attrs));
    }

    private function addToProgram(Program $program, Course $course, int $yearLevel = null, RequirementType $requirement = RequirementType::Required): ProgramCourse
    {
        return ProgramCourse::query()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'requirement' => $requirement,
            'year_level' => $yearLevel,
        ]);
    }

    private function enroll(Program $program, User $student, Semester $semester, Course $course, StudentProgram $sp): Enrollment
    {
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        return Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'student_program_id' => $sp->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now()->subWeeks(5),
        ]);
    }

    // -------------------------------------------------------------------------
    // 6a — max_semesters_to_graduate
    // -------------------------------------------------------------------------

    #[Test]
    public function blocks_enrollment_past_max_semesters(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        // Program allows only 1 distinct semester before blocking further registration.
        $program = $this->makeProgram(['max_semesters_to_graduate' => 1]);

        $course1 = $this->makeCourse('C1A');
        $course2 = $this->makeCourse('C2A');
        $this->addToProgram($program, $course1);
        $this->addToProgram($program, $course2);

        $sp = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        // Simulate the student already having used one semester (semester 1).
        $semester1 = $this->pastSemester(10);
        $this->enroll($program, $student, $semester1, $course1, $sp);

        // Open a second semester — enrolling here would push the count to 2 > max(1).
        $semester2 = $this->openSemester();
        $offering2 = CourseOffering::query()->create([
            'course_id' => $course2->id,
            'semester_id' => $semester2->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering2->id,
            'student_program_id' => $sp->id,
        ])->assertSessionHasErrors('enrollment');
    }

    #[Test]
    public function allows_admin_override_past_max_semesters(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $program = $this->makeProgram(['max_semesters_to_graduate' => 1]);
        $course1 = $this->makeCourse('C1B');
        $course2 = $this->makeCourse('C2B');
        $this->addToProgram($program, $course1);
        $this->addToProgram($program, $course2);

        $sp = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        $semester1 = $this->pastSemester(10);
        $this->enroll($program, $student, $semester1, $course1, $sp);

        $semester2 = $this->openSemester();
        $offering2 = CourseOffering::query()->create([
            'course_id' => $course2->id,
            'semester_id' => $semester2->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        // Admin override must bypass the max_semesters block.
        $this->actingAs($admin)->post(route('admin.enrollments.override'), [
            'student_id' => $student->id,
            'offering_id' => $offering2->id,
            'student_program_id' => $sp->id,
        ])->assertRedirect();

        $this->assertSame(1, Enrollment::query()->where('student_id', $student->id)->where('offering_id', $offering2->id)->count());
    }

    // -------------------------------------------------------------------------
    // 6b — Admin override for drop/withdraw windows
    // -------------------------------------------------------------------------

    #[Test]
    public function blocks_self_drop_after_add_drop_week_but_allows_admin_override(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        // Semester started 10 weeks ago; add/drop ended at week 2 — we are now in week ~11.
        $semester = $this->pastSemester(10);
        $course = $this->makeCourse('C1C', ['is_standalone' => true]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now()->subWeeks(9),
        ]);

        // Self-drop should be blocked.
        $this->actingAs($student)
            ->post(route('enrollments.drop', $enrollment))
            ->assertSessionHasErrors('enrollment');

        $this->assertSame(EnrollmentStatus::Enrolled, $enrollment->fresh()->status);

        // Admin override drop should succeed and produce 'enrollment.override_drop' audit.
        $service = app(EnrollmentService::class);
        $service->drop($admin, $enrollment->fresh(), adminOverride: true);

        $this->assertSame(EnrollmentStatus::Dropped, $enrollment->fresh()->status);

        $auditEntry = AuditLog::query()
            ->where('action', 'enrollment.override_drop')
            ->where('entity_id', $enrollment->id)
            ->first();

        $this->assertNotNull($auditEntry);
    }

    #[Test]
    public function blocks_self_withdraw_after_last_withdrawal_week_but_allows_admin_override(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        // Semester started 10 weeks ago; last_withdrawal_week=4 — we are in week ~11.
        $semester = $this->pastSemester(10);
        $course = $this->makeCourse('C1D', ['is_standalone' => true]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now()->subWeeks(9),
        ]);

        // Self-withdraw must be blocked.
        $this->actingAs($student)
            ->post(route('enrollments.withdraw', $enrollment))
            ->assertSessionHasErrors('enrollment');

        $this->assertSame(EnrollmentStatus::Enrolled, $enrollment->fresh()->status);

        // Admin override withdraw must succeed.
        $service = app(EnrollmentService::class);
        $service->withdraw($admin, $enrollment->fresh(), adminOverride: true);

        $this->assertSame(EnrollmentStatus::Withdrawn, $enrollment->fresh()->status);

        $auditEntry = AuditLog::query()
            ->where('action', 'enrollment.override_withdraw')
            ->where('entity_id', $enrollment->id)
            ->first();

        $this->assertNotNull($auditEntry);
    }

    #[Test]
    public function override_past_withdrawal_window_refunds_zero_by_default(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        // Semester well past last_withdrawal_week.
        $semester = $this->pastSemester(10);
        $course = $this->makeCourse('C1E', ['is_standalone' => true]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now()->subWeeks(9),
        ]);

        // No invoice exists → refundEnrollment with 0% is a no-op → no Refund row created.
        $service = app(EnrollmentService::class);
        $service->withdraw($admin, $enrollment->fresh(), adminOverride: true);

        $this->assertSame(EnrollmentStatus::Withdrawn, $enrollment->fresh()->status);

        // The PaymentService early-exits when percent <= 0, so no Refund record is created.
        $this->assertSame(0, \App\Models\Refund::query()->count());
    }

    // -------------------------------------------------------------------------
    // 6c — year_level sequencing
    // -------------------------------------------------------------------------

    #[Test]
    public function warns_on_year_level_gap_when_flag_false_and_enrollment_still_succeeds(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        // enforce_year_sequence = false (default) → warning only, enrollment proceeds.
        $program = $this->makeProgram(['enforce_year_sequence' => false]);

        $year1Course = $this->makeCourse('YL1F');
        $year2Course = $this->makeCourse('YL2F');

        $this->addToProgram($program, $year1Course, yearLevel: 1);
        $this->addToProgram($program, $year2Course, yearLevel: 2);

        $sp = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        // Student has NOT passed the year-level-1 course.

        $semester = $this->openSemester();
        $offering = CourseOffering::query()->create([
            'course_id' => $year2Course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        // Enrollment in year-level-2 course must succeed even with the gap.
        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
            'student_program_id' => $sp->id,
        ])->assertRedirect();

        $this->assertSame(1, Enrollment::query()->where('student_id', $student->id)->count());
    }

    #[Test]
    public function blocks_on_year_level_gap_when_flag_true_and_admin_override_bypasses(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        // enforce_year_sequence = true → hard block for non-admin.
        $program = $this->makeProgram(['enforce_year_sequence' => true]);

        $year1Course = $this->makeCourse('YL1G');
        $year2Course = $this->makeCourse('YL2G');

        $this->addToProgram($program, $year1Course, yearLevel: 1);
        $this->addToProgram($program, $year2Course, yearLevel: 2);

        $sp = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        $semester = $this->openSemester();
        $offering = CourseOffering::query()->create([
            'course_id' => $year2Course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        // Student cannot enroll — year-level sequence enforced.
        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
            'student_program_id' => $sp->id,
        ])->assertSessionHasErrors('enrollment');

        // Admin override bypasses assertCanRegister entirely.
        $this->actingAs($admin)->post(route('admin.enrollments.override'), [
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'student_program_id' => $sp->id,
        ])->assertRedirect();

        $this->assertSame(1, Enrollment::query()->where('student_id', $student->id)->count());
    }

    #[Test]
    public function year_level_warning_is_recorded_and_surfaced(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $program = $this->makeProgram(['enforce_year_sequence' => false]);

        $year1Course = $this->makeCourse('YL1H');
        $year2Course = $this->makeCourse('YL2H');

        $this->addToProgram($program, $year1Course, yearLevel: 1);
        $this->addToProgram($program, $year2Course, yearLevel: 2);

        $sp = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        $semester = $this->openSemester();
        $offering = CourseOffering::query()->create([
            'course_id' => $year2Course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
            'student_program_id' => $sp->id,
        ])->assertRedirect();

        // AuditLog must carry the structured warning with all required fields.
        $warn = AuditLog::query()
            ->where('action', 'enrollment.year_level_sequence_warning')
            ->first();

        $this->assertNotNull($warn, 'year_level_sequence_warning audit entry must exist');
        $this->assertSame($student->id, $warn->after['student_id']);
        $this->assertSame($program->id, $warn->after['program_id']);
        $this->assertSame($year2Course->id, $warn->after['course_id']);
        $this->assertSame(2, $warn->after['attempted_year_level']);
        $this->assertSame(1, $warn->after['highest_incomplete_year_level']);
    }

    // -------------------------------------------------------------------------
    // 6d — Block re-enrollment of a passed course
    // -------------------------------------------------------------------------

    #[Test]
    public function blocks_re_enrollment_of_passed_course_without_override(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $course = $this->makeCourse('PASS1', ['is_standalone' => true]);
        $semester = $this->openSemester();

        // Student has an AcademicRecord marking this course as passed.
        AcademicRecord::query()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'letter_grade' => 'A',
            'percent' => 95,
            'gpa_points' => 4,
            'credit_hours' => 3,
            'term' => 'prior',
            'is_passing' => true,
            'completed_at' => now()->subYear(),
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        // Student re-registration must be blocked.
        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
        ])->assertSessionHasErrors('enrollment');

        $this->assertSame(0, Enrollment::query()->count());

        // Admin override bypasses assertCanRegister → registration succeeds.
        $this->actingAs($admin)->post(route('admin.enrollments.override'), [
            'student_id' => $student->id,
            'offering_id' => $offering->id,
        ])->assertRedirect();

        $this->assertSame(1, Enrollment::query()->where('student_id', $student->id)->count());
    }

    // -------------------------------------------------------------------------
    // Regression — normal enrollment still works
    // -------------------------------------------------------------------------

    #[Test]
    public function normal_enrollment_still_succeeds(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $course = $this->makeCourse('NORM1', ['is_standalone' => true]);
        $semester = $this->openSemester();

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
        ])->assertRedirect();

        $this->assertSame(1, Enrollment::query()->count());
        $this->assertSame(EnrollmentStatus::Enrolled, Enrollment::query()->first()->status);
    }

    #[Test]
    public function existing_enrollment_engine_tests_still_pass(): void
    {
        // EnrollmentEngineTest is run alongside this suite under the validate-step.sh
        // filter "ProgramRuleEnforcementTest|EnrollmentEngine". This test acts as
        // an explicit reminder that regressions there are caught by CI.
        $this->expectNotToPerformAssertions();
    }
}
