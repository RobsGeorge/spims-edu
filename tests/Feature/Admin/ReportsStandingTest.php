<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicStanding;
use App\Enums\ApplicationStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\ClassSessionMode;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
use App\Enums\OfferingMode;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\AttendanceEntry;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Gradebook\GradebookService;
use App\Services\Reports\AcademicStandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsStandingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     dean: User,
     *     instructor: User,
     *     program: Program,
     *     course: Course,
     *     offering: CourseOffering,
     *     semester: Semester,
     *     passing: StudentProgram,
     *     probation: StudentProgram,
     *     passingStudent: User,
     *     probationStudent: User
     * }
     */
    private function lockedCohort(): array
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $dean = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $passingStudent = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Pat',
            'last_name' => 'Pass',
        ]);
        $probationStudent = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Riley',
            'last_name' => 'Risk',
        ]);

        $program = Program::query()->create([
            'code' => 'DIP1',
            'name' => 'Diploma One',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);

        $course = Course::query()->create([
            'code' => 'THEO101',
            'title' => 'Theology I',
            'credit_hours' => 3,
            'is_standalone' => false,
            'active' => true,
        ]);

        ProgramCourse::query()->create([
            'program_id' => $program->id,
            'course_id' => $course->id,
            'requirement' => RequirementType::Required,
        ]);

        $year = AcademicYear::query()->create([
            'name' => '2026-2027',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
        ]);
        $semester = Semester::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'Fall 2026',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(3),
            'registration_start' => now()->subMonth(),
            'registration_end' => now()->addWeek(),
            'add_drop_end_week' => 2,
            'last_withdrawal_week' => 8,
            'withdrawal_refund_percent' => 0,
            'status' => 'OPEN',
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
        $this->staffOffering($instructor, $offering);

        $passing = StudentProgram::query()->create([
            'student_id' => $passingStudent->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);
        $probation = StudentProgram::query()->create([
            'student_id' => $probationStudent->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        Enrollment::query()->create([
            'student_id' => $passingStudent->id,
            'offering_id' => $offering->id,
            'student_program_id' => $passing->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'final_percent' => 95,
            'final_letter' => 'A',
            'final_gpa_points' => 4,
            'grade_status' => GradeStatus::Submitted,
            'grade_type' => GradeType::Standard,
        ]);
        Enrollment::query()->create([
            'student_id' => $probationStudent->id,
            'offering_id' => $offering->id,
            'student_program_id' => $probation->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'final_percent' => 65,
            'final_letter' => 'D',
            'final_gpa_points' => 1,
            'grade_status' => GradeStatus::Submitted,
            'grade_type' => GradeType::Standard,
        ]);

        app(GradebookService::class)->lockGrades($instructor, $offering);

        return compact(
            'dean',
            'instructor',
            'program',
            'course',
            'offering',
            'semester',
            'passing',
            'probation',
            'passingStudent',
            'probationStudent',
        );
    }

    #[Test]
    public function locked_cohort_csv_has_headcount_and_grade_rows(): void
    {
        $bundle = $this->lockedCohort();
        $dean = $bundle['dean'];

        $this->actingAs($dean)
            ->get(route('admin.reports.headcount'))
            ->assertOk()
            ->assertSee(__('reports.headcount_title'))
            ->assertSee('DIP1')
            ->assertSee('THEO101')
            ->assertSee('Fall 2026')
            ->assertSee(__('reports.download_csv'));

        $headcount = $this->actingAs($dean)
            ->get(route('admin.reports.csv', 'headcount'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString(__('reports.col_program_code'), $headcount);
        $this->assertStringContainsString('DIP1', $headcount);
        $this->assertStringContainsString('THEO101', $headcount);
        $this->assertStringContainsString('Fall 2026', $headcount);

        $this->actingAs($dean)
            ->get(route('admin.reports.grades'))
            ->assertOk()
            ->assertSee(__('reports.grades_title'))
            ->assertSee('A')
            ->assertSee('D');

        $grades = $this->actingAs($dean)
            ->get(route('admin.reports.csv', 'grades'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString(__('reports.col_letter'), $grades);
        $this->assertStringContainsString('THEO101', $grades);
        $this->assertStringContainsString('A', $grades);
        $this->assertStringContainsString('D', $grades);
    }

    #[Test]
    public function standing_flips_to_probation_when_gpa_drops_below_two(): void
    {
        $bundle = $this->lockedCohort();

        $this->assertSame(AcademicStanding::Good, $bundle['passing']->fresh()->academic_standing);
        $this->assertSame(4.0, $bundle['passing']->fresh()->cached_gpa);

        $probation = $bundle['probation']->fresh();
        $this->assertSame(1.0, $probation->cached_gpa);
        $this->assertSame(AcademicStanding::Probation, $probation->academic_standing);

        $this->actingAs($bundle['dean'])
            ->get(route('admin.reports.standing'))
            ->assertOk()
            ->assertSee(__('reports.standing_title'))
            ->assertSee('Riley')
            ->assertDontSee('Pat Pass', false);
    }

    #[Test]
    public function student_is_forbidden_on_reports_and_csv(): void
    {
        $this->lockedCohort();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.reports.headcount'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.reports.attendance'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.reports.csv', 'headcount'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.reports.csv', 'finance'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.finance.reports'))->assertForbidden();
    }

    #[Test]
    public function dean_can_open_finance_aging_and_export_attendance_csv(): void
    {
        $bundle = $this->lockedCohort();
        $dean = $bundle['dean'];
        $offering = $bundle['offering'];
        $student = $bundle['passingStudent'];

        $session = ClassSession::query()->create([
            'offering_id' => $offering->id,
            'title' => 'Lecture 1',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson,
            'lock_version' => 0,
        ]);
        AttendanceEntry::query()->create([
            'class_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => AttendanceStatus::Present,
            'source' => AttendanceSource::Manual,
            'recorded_by_id' => $bundle['instructor']->id,
            'recorded_at' => now(),
        ]);

        $this->actingAs($dean)
            ->get(route('hubs.academic'))
            ->assertOk()
            ->assertSee(__('hubs.reports'));

        $this->actingAs($dean)
            ->get(route('admin.reports.attendance'))
            ->assertOk()
            ->assertSee(__('reports.attendance_title'))
            ->assertSee('THEO101')
            ->assertSee(__('reports.download_csv'));

        $csv = $this->actingAs($dean)
            ->get(route('admin.reports.csv', 'attendance'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('THEO101', $csv);
        $this->assertStringContainsString(__('reports.col_attendance_percent'), $csv);

        $this->actingAs($dean)
            ->get(route('admin.finance.reports'))
            ->assertOk()
            ->assertSee(__('finance.aging_title'))
            ->assertSee(__('finance.aging_0_14'));
    }

    #[Test]
    public function csv_download_writes_audit_log(): void
    {
        $bundle = $this->lockedCohort();

        $this->actingAs($bundle['dean'])
            ->get(route('admin.reports.csv', 'headcount'))
            ->assertOk();

        $this->assertTrue(
            AuditLog::query()->where('action', 'reports.csv')->where('entity_id', 'headcount')->exists()
        );
    }

    #[Test]
    public function admissions_funnel_table_lists_status_counts(): void
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $dean = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $program = Program::query()->create([
            'code' => 'ADM1',
            'name' => 'Admissions Program',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);
        $form = ApplicationForm::query()->create([
            'program_id' => $program->id,
            'name' => 'Main',
            'active' => true,
        ]);

        foreach ([ApplicationStatus::Submitted, ApplicationStatus::Accepted] as $status) {
            $applicant = User::factory()->withRole(RoleType::Student)->create();
            Application::query()->create([
                'applicant_id' => $applicant->id,
                'program_id' => $program->id,
                'form_id' => $form->id,
                'status' => $status,
            ]);
        }

        $this->actingAs($dean)
            ->get(route('admin.reports.admissions'))
            ->assertOk()
            ->assertSee(ApplicationStatus::Submitted->value)
            ->assertSee(ApplicationStatus::Accepted->value);
    }

    #[Test]
    public function academic_admin_can_post_thresholds_and_reapply_standing(): void
    {
        $bundle = $this->lockedCohort();
        $dean = $bundle['dean'];

        $mid = StudentProgram::query()->create([
            'student_id' => User::factory()->withRole(RoleType::Student)->create()->id,
            'program_id' => $bundle['program']->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
            'cached_gpa' => 1.50,
        ]);
        app(AcademicStandingService::class)->apply($mid);
        $this->assertSame(AcademicStanding::Probation, $mid->fresh()->academic_standing);

        $this->actingAs($dean)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee(__('reports.thresholds_title'));

        $this->actingAs($dean)
            ->get(route('admin.reports.standing'))
            ->assertOk()
            ->assertSee(__('reports.edit_thresholds'));

        $this->actingAs($dean)
            ->get(route('admin.reports.standing.thresholds'))
            ->assertOk()
            ->assertSee(__('reports.good_min'))
            ->assertSee(__('reports.suspension_below'))
            ->assertSee(__('reports.thresholds_help'));

        $this->actingAs($dean)
            ->post(route('admin.reports.standing.thresholds.update'), [
                'good_min' => 200,
                'suspension_below' => 160,
            ])
            ->assertRedirect(route('admin.reports.standing.thresholds'));

        $this->assertSame(AcademicStanding::Suspension, $mid->fresh()->academic_standing);
        $this->assertSame(
            ['good_min' => 200, 'suspension_below' => 160],
            Setting::query()->find(AcademicStandingService::SETTING_KEY)?->value
        );
        $log = AuditLog::query()->where('action', 'academic_standing.thresholds')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->entity_id);
        $this->assertSame(AcademicStandingService::SETTING_KEY, $log->after['key'] ?? null);
    }

    #[Test]
    public function student_and_finance_admin_cannot_change_standing_thresholds(): void
    {
        $this->lockedCohort();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $bursar = User::factory()->withRole(RoleType::FinancialAdmin)->create();

        $this->actingAs($student)->get(route('admin.reports.standing.thresholds'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.reports.standing.thresholds.update'), [
            'good_min' => 200,
            'suspension_below' => 160,
        ])->assertForbidden();

        $this->actingAs($bursar)->get(route('admin.reports.index'))->assertOk()->assertDontSee(__('reports.thresholds_title'));
        $this->actingAs($bursar)->get(route('admin.reports.standing'))->assertOk()->assertDontSee(__('reports.edit_thresholds'));
        $this->actingAs($bursar)->get(route('admin.reports.standing.thresholds'))->assertForbidden();
        $this->actingAs($bursar)->post(route('admin.reports.standing.thresholds.update'), [
            'good_min' => 200,
            'suspension_below' => 160,
        ])->assertForbidden();
    }

    #[Test]
    public function program_override_puts_mid_gpa_on_probation_while_inherit_stays_good(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $dean = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $programA = $this->makeProgram('DIPA', 'Diploma A');
        $programB = $this->makeProgram('DIPB', 'Diploma B');

        $spA = StudentProgram::query()->create([
            'student_id' => User::factory()->withRole(RoleType::Student)->create()->id,
            'program_id' => $programA->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
            'cached_gpa' => 2.20,
        ]);
        $spB = StudentProgram::query()->create([
            'student_id' => User::factory()->withRole(RoleType::Student)->create()->id,
            'program_id' => $programB->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
            'cached_gpa' => 2.20,
        ]);

        $service = app(AcademicStandingService::class);
        $service->apply($spA);
        $service->apply($spB);
        $this->assertSame(AcademicStanding::Good, $spA->fresh()->academic_standing);
        $this->assertSame(AcademicStanding::Good, $spB->fresh()->academic_standing);

        $service->updateProgramOverrides($dean, $programA, 250, 150);

        $this->assertSame(250, $programA->fresh()->standing_good_min);
        $this->assertSame(150, $programA->fresh()->standing_suspension_below);
        $this->assertSame(AcademicStanding::Probation, $spA->fresh()->academic_standing);
        $this->assertSame(AcademicStanding::Good, $spB->fresh()->academic_standing);
        $this->assertSame('program', $service->thresholdsFor($programA->fresh())['source']);
        $this->assertSame('school', $service->thresholdsFor($programB->fresh())['source']);

        $service->apply($spA->fresh());
        $this->assertSame(AcademicStanding::Probation, $spA->fresh()->academic_standing);
    }

    #[Test]
    public function academic_admin_can_get_program_standing_section_and_post_override(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $dean = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $program = $this->makeProgram('DEAN', 'Dean Diploma');

        $this->actingAs($dean)
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee(__('reports.program_thresholds_title'))
            ->assertSee(__('reports.program_thresholds_help'));

        $this->actingAs($dean)
            ->get(route('admin.programs.edit', $program))
            ->assertOk()
            ->assertSee(__('reports.program_thresholds_title'))
            ->assertSee(__('academics.edit_program'));

        $this->actingAs($dean)
            ->post(route('admin.programs.standing.update', $program), [
                'good_min' => 250,
                'suspension_below' => 150,
            ])
            ->assertRedirect(route('admin.programs.show', $program).'#standing');

        $program->refresh();
        $this->assertSame(250, $program->standing_good_min);
        $this->assertSame(150, $program->standing_suspension_below);

        $log = AuditLog::query()->where('action', 'academic_standing.program_thresholds')->first();
        $this->assertNotNull($log);
        $this->assertSame($program->id, $log->entity_id);
        $this->assertSame('Program', $log->entity_type);
    }

    #[Test]
    public function administrative_admin_can_post_program_override_without_programs_manage(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $program = $this->makeProgram('ADMN', 'Admin Diploma');

        $this->actingAs($admin)
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee(__('reports.program_thresholds_title'));

        $this->actingAs($admin)
            ->get(route('admin.programs.edit', $program))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.programs.standing.update', $program), [
                'good_min' => 280,
                'suspension_below' => 120,
            ])
            ->assertRedirect(route('admin.programs.show', $program).'#standing');

        $this->assertSame(280, $program->fresh()->standing_good_min);
        $this->assertSame(120, $program->fresh()->standing_suspension_below);
        $this->assertTrue(
            AuditLog::query()->where('action', 'academic_standing.program_thresholds')->where('entity_id', $program->id)->exists()
        );
    }

    #[Test]
    public function student_is_forbidden_on_program_standing_override_post(): void
    {
        $program = $this->makeProgram('NOPE', 'Student Locked');
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->post(route('admin.programs.standing.update', $program), [
                'good_min' => 250,
                'suspension_below' => 150,
            ])
            ->assertForbidden();

        $this->assertNull($program->fresh()->standing_good_min);
        $this->assertNull($program->fresh()->standing_suspension_below);
    }

    #[Test]
    public function clearing_program_override_returns_student_to_school_wide_good(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $dean = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $program = $this->makeProgram('CLR1', 'Clear Diploma');
        $sp = StudentProgram::query()->create([
            'student_id' => User::factory()->withRole(RoleType::Student)->create()->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
            'cached_gpa' => 2.20,
        ]);

        $service = app(AcademicStandingService::class);
        $service->updateProgramOverrides($dean, $program, 250, 150);
        $this->assertSame(AcademicStanding::Probation, $sp->fresh()->academic_standing);
        $this->assertSame('program', $service->thresholdsFor($program->fresh())['source']);

        $this->actingAs($dean)
            ->post(route('admin.programs.standing.update', $program), [
                'good_min' => null,
                'suspension_below' => null,
            ])
            ->assertRedirect(route('admin.programs.show', $program).'#standing');

        $program->refresh();
        $this->assertNull($program->standing_good_min);
        $this->assertNull($program->standing_suspension_below);
        $this->assertSame(AcademicStanding::Good, $sp->fresh()->academic_standing);
        $this->assertSame('school', $service->thresholdsFor($program->fresh())['source']);
    }

    #[Test]
    public function program_override_rejects_half_filled_and_inverted_cutoffs(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $dean = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $program = $this->makeProgram('BAD1', 'Invalid Diploma');

        $this->actingAs($dean)
            ->postJson(route('admin.programs.standing.update', $program), [
                'good_min' => 250,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['good_min', 'suspension_below']);

        $this->actingAs($dean)
            ->postJson(route('admin.programs.standing.update', $program), [
                'good_min' => 150,
                'suspension_below' => 200,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('suspension_below');

        $this->assertNull($program->fresh()->standing_good_min);
        $this->assertNull($program->fresh()->standing_suspension_below);
    }

    #[Test]
    public function school_wide_thresholds_page_lists_program_override_entrance(): void
    {
        $bundle = $this->lockedCohort();
        $dean = $bundle['dean'];
        $program = $bundle['program'];

        app(AcademicStandingService::class)->updateProgramOverrides($dean, $program, 250, 150);

        $this->actingAs($dean)
            ->get(route('admin.reports.standing.thresholds'))
            ->assertOk()
            ->assertSee(__('reports.program_overrides_title'))
            ->assertSee('DIP1')
            ->assertSee(__('reports.program_thresholds_source_program'))
            ->assertSee(__('reports.edit_program_thresholds'));
    }

    private function makeProgram(string $code, string $name): Program
    {
        return Program::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'active' => true,
        ]);
    }
}
