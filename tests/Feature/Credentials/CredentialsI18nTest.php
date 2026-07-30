<?php

namespace Tests\Feature\Credentials;

use App\Enums\CredentialType;
use App\Enums\OfferingMode;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Credential;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\ProgramRequirementFulfillment;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Credentials\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CredentialsI18nTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function issue_transcript_and_public_verify_page(): void
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Mary',
            'last_name' => 'Shenouda',
        ]);

        $course = Course::query()->create([
            'code' => 'TH101',
            'title' => 'Theology',
            'credit_hours' => 3,
            'active' => true,
        ]);

        AcademicRecord::query()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'letter_grade' => 'A',
            'percent' => 95,
            'gpa_points' => 4,
            'credit_hours' => 3,
            'term' => 'Fall',
            'is_passing' => true,
            'completed_at' => now(),
        ]);

        $this->actingAs($adm)->post(route('admin.credentials.store'), [
            'student_id' => $student->id,
            'type' => CredentialType::Transcript->value,
            'language' => 'en',
        ])->assertRedirect();

        $credential = Credential::query()->first();
        $this->assertNotNull($credential);
        $this->assertMatchesRegularExpression('/^SPIMS-CRED-\d{4}-\d{5}$/', $credential->serial);

        $this->get(route('credentials.verify', $credential->qr_token))
            ->assertOk()
            ->assertSee(__('credentials.valid'))
            ->assertSee($credential->serial)
            ->assertSee('Mary');

        $this->actingAs($student)->get(route('transcript.show'))
            ->assertOk()
            ->assertSee('TH101')
            ->assertSee('4');
    }

    #[Test]
    public function program_certificate_requires_fulfillments_and_regenerate_revokes_old(): void
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $program = Program::query()->create([
            'code' => 'DIP',
            'name' => 'Diploma',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 12,
            'max_courses_per_semester' => 4,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 0,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'signatory_name' => 'Bishop Pachomius',
            'signatory_title' => 'Dean',
            'active' => true,
        ]);

        $course = Course::query()->create(['code' => 'C1', 'title' => 'C1', 'credit_hours' => 3, 'active' => true]);
        $pc = ProgramCourse::query()->create([
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

        $this->actingAs($aca)->post(route('admin.credentials.store'), [
            'student_id' => $student->id,
            'type' => CredentialType::ProgramCertificate->value,
            'program_id' => $program->id,
        ])->assertSessionHasErrors();

        $record = AcademicRecord::query()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'letter_grade' => 'B',
            'percent' => 85,
            'gpa_points' => 3,
            'credit_hours' => 3,
            'term' => 'Spring',
            'is_passing' => true,
            'completed_at' => now(),
        ]);

        ProgramRequirementFulfillment::query()->create([
            'student_program_id' => $sp->id,
            'program_course_id' => $pc->id,
            'academic_record_id' => $record->id,
            'applied_at' => now(),
        ]);

        $credential = app(CredentialService::class)->issueProgramCertificate($aca, $student, $program, 'ar');
        $this->assertSame('Bishop Pachomius', $credential->signatory_name);
        $this->assertSame('ar', $credential->language);

        $new = app(CredentialService::class)->regenerate($aca, $credential);
        $this->assertNotNull($credential->fresh()->revoked_at);
        $this->assertNotSame($credential->serial, $new->serial);
        $this->assertTrue($new->isValid());

        $this->get(route('credentials.verify', $credential->qr_token))
            ->assertOk()
            ->assertSee(__('credentials.revoked'));
    }

    #[Test]
    public function standalone_certificate_and_locale_rtl_layout(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $course = Course::query()->create([
            'code' => 'STAND1',
            'title' => 'Iconography',
            'credit_hours' => 2,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);

        AcademicRecord::query()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'letter_grade' => 'A',
            'percent' => 92,
            'gpa_points' => 4,
            'credit_hours' => 2,
            'term' => 'self-paced',
            'is_passing' => true,
            'completed_at' => now(),
        ]);

        $credential = app(CredentialService::class)->issueStandaloneCertificate($adm, $student, $offering);
        $this->assertSame(CredentialType::StandaloneCertificate, $credential->type);

        $this->withCookie('locale', 'ar')
            ->get(route('home'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('spims-skip-link', false)
            ->assertSee('IBM+Plex+Sans+Arabic', false);
    }
}
