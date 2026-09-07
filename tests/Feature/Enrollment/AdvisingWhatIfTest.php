<?php

namespace Tests\Feature\Enrollment;

use App\Enums\AdvisingHoldKind;
use App\Enums\OfferingMode;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Exceptions\AuthorizationException;
use App\Models\AcademicRecord;
use App\Models\AdvisingHold;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\ProgramRequirementFulfillment;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Completion\StudentNoteService;
use App\Services\Enrollment\AdvisingService;
use App\Services\Enrollment\DegreeAuditService;
use App\Services\Enrollment\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdvisingWhatIfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{program: Program, required: Course, elective: Course, sp: StudentProgram}
     */
    private function diplomaWithElective(User $student): array
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);

        $program = Program::query()->create([
            'code' => 'DIP',
            'name' => 'Diploma',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 12,
            'max_courses_per_semester' => 4,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 3,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);

        $required = Course::query()->create([
            'code' => 'REQ1',
            'title' => 'Required',
            'credit_hours' => 3,
            'active' => true,
        ]);
        $elective = Course::query()->create([
            'code' => 'ET101',
            'title' => 'Elective topics',
            'credit_hours' => 3,
            'active' => true,
        ]);

        ProgramCourse::query()->create([
            'program_id' => $program->id,
            'course_id' => $required->id,
            'requirement' => RequirementType::Required,
        ]);
        ProgramCourse::query()->create([
            'program_id' => $program->id,
            'course_id' => $elective->id,
            'requirement' => RequirementType::Elective,
        ]);

        $sp = StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        return compact('program', 'required', 'elective', 'sp');
    }

    private function standaloneOffering(string $code = 'FREE1'): CourseOffering
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => 'Standalone',
            'credit_hours' => 2,
            'is_standalone' => true,
            'active' => true,
        ]);

        return CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
    }

    #[Test]
    public function active_advising_hold_blocks_registration_then_release_allows_it(): void
    {
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->standaloneOffering();

        $this->actingAs($admin)
            ->post(route('advising.holds.store', $student), [
                'kind' => AdvisingHoldKind::Advising->value,
                'reason' => 'Meet your advisor first',
            ])
            ->assertRedirect();

        $this->actingAs($student)
            ->postJson(route('enrollments.store'), [
                'offering_id' => $offering->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('enrollment');

        $this->assertSame(
            'hold',
            app(EnrollmentService::class)->registrationConflict($student, $offering)
        );

        $hold = AdvisingHold::query()->where('student_id', $student->id)->first();
        $this->actingAs($admin)
            ->post(route('advising.holds.release', $hold))
            ->assertRedirect();

        $this->actingAs($student)
            ->post(route('enrollments.store'), [
                'offering_id' => $offering->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, Enrollment::query()->where('student_id', $student->id)->count());
    }

    #[Test]
    public function what_if_does_not_write_academic_records_and_elective_reduces_remaining(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $bundle = $this->diplomaWithElective($student);
        $audit = app(DegreeAuditService::class);

        $before = $audit->audit($student, $bundle['sp']);
        $this->assertSame(0, $before['elective_credits_met']);
        $this->assertSame(3, $before['elective_credits_required'] - $before['elective_credits_met']);
        $this->assertFalse($before['what_if']);

        $records = AcademicRecord::query()->count();
        $fulfillments = ProgramRequirementFulfillment::query()->count();

        $whatIf = $audit->whatIf($bundle['sp'], [$bundle['elective']->id]);

        $this->assertTrue($whatIf['what_if']);
        $this->assertSame(3, $whatIf['elective_credits_met']);
        $this->assertLessThan(
            $before['elective_credits_required'] - $before['elective_credits_met'],
            $whatIf['elective_credits_required'] - $whatIf['elective_credits_met']
        );
        $this->assertFalse(collect($whatIf['remaining'])->contains('code', 'ET101'));
        $this->assertSame($records, AcademicRecord::query()->count());
        $this->assertSame($fulfillments, ProgramRequirementFulfillment::query()->count());
    }

    #[Test]
    public function student_cannot_read_another_students_notes(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $course = Course::query()->create([
            'code' => 'NOTEA',
            'title' => 'Notes',
            'credit_hours' => 1,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);
        $owner = User::factory()->withRole(RoleType::Student)->create();
        $other = User::factory()->withRole(RoleType::Student)->create();

        app(StudentNoteService::class)->add($instructor, $offering, $owner, 'Private note');

        $this->expectException(AuthorizationException::class);
        app(StudentNoteService::class)->forStudent($other, $offering, $owner);
    }

    #[Test]
    public function instructor_cannot_place_a_hold_on_a_student_they_do_not_advise(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($instructor)
            ->post(route('advising.holds.store', $student), [
                'kind' => AdvisingHoldKind::Advising->value,
                'reason' => 'Not my advisee',
            ])
            ->assertForbidden();

        $this->assertSame(0, AdvisingHold::query()->count());
    }

    #[Test]
    public function student_is_forbidden_on_assign_and_another_students_what_if(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $other = User::factory()->withRole(RoleType::Student)->create();
        $advisor = User::factory()->withRole(RoleType::Instructor)->create();
        $bundle = $this->diplomaWithElective($other);

        $this->actingAs($student)
            ->post(route('advising.assign'), [
                'student_id' => $other->id,
                'advisor_id' => $advisor->id,
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('advising.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('enrollments.audit', $bundle['sp']).'?'.http_build_query([
                'hypothetical_course_ids' => [$bundle['elective']->id],
            ]))
            ->assertForbidden();
    }

    #[Test]
    public function advisor_can_open_advisee_audit_and_what_if_form(): void
    {
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $advisor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $bundle = $this->diplomaWithElective($student);

        $this->actingAs($admin)
            ->get(route('advising.index'))
            ->assertOk()
            ->assertSee(__('advising.assign_title'), false);

        $this->actingAs($admin)
            ->post(route('advising.assign'), [
                'student_id' => $student->id,
                'advisor_id' => $advisor->id,
                'program_id' => $bundle['program']->id,
            ])
            ->assertRedirect();

        $this->actingAs($advisor)
            ->get(route('advising.index'))
            ->assertOk()
            ->assertSee($student->first_name, false);

        $this->actingAs($advisor)
            ->get(route('advising.show', $student))
            ->assertOk()
            ->assertSee(__('advising.place_hold'), false);

        $this->actingAs($advisor)
            ->post(route('advising.holds.store', $student), [
                'kind' => AdvisingHoldKind::Advising->value,
                'reason' => 'Plan your electives',
            ])
            ->assertRedirect();

        $this->actingAs($advisor)
            ->get(route('enrollments.audit', $bundle['sp']))
            ->assertOk()
            ->assertSee(__('advising.what_if'), false)
            ->assertSee('ET101', false);

        $this->actingAs($advisor)
            ->get(route('enrollments.audit', $bundle['sp']).'?'.http_build_query([
                'hypothetical_course_ids' => [$bundle['elective']->id],
            ]))
            ->assertOk()
            ->assertSee(__('advising.what_if_active'), false)
            ->assertSee('0', false);

        $this->actingAs($student)
            ->get(route('enrollments.audit', $bundle['sp']))
            ->assertOk()
            ->assertSee(__('advising.what_if'), false);

        $this->assertTrue(app(AdvisingService::class)->isAssignedAdvisor($advisor, $student));
    }

    #[Test]
    public function student_cannot_open_advising_show_for_notes(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $other = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->get(route('advising.show', $other))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('advising.show', $student))
            ->assertForbidden();
    }
}
