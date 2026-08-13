<?php

namespace Tests\Feature\Portal;

use App\Enums\ApplicationStatus;
use App\Enums\ProgramType;
use App\Enums\RoleType;
use App\Enums\TranslationSource;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\Theme;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalD5OperatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function academic_admin_can_open_grading_schemes_page(): void
    {
        $this->seed();
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $this->actingAs($aca)
            ->get(route('admin.grading-schemes.index'))
            ->assertOk()
            ->assertSee(__('hubs.grading_schemes'))
            ->assertSee('SPIMS Default');
    }

    #[Test]
    public function academic_admin_sees_translations_verify_inbox(): void
    {
        $this->seed();
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        Translation::query()->create([
            'entity_type' => 'Course',
            'entity_id' => '01TRANSLATIONTESTENTITY000001',
            'field' => 'title',
            'locale' => 'ar',
            'value' => 'تاريخ الكنيسة',
            'source' => TranslationSource::Ai,
            'verified' => false,
            'updated_by_id' => $aca->id,
        ]);

        $this->actingAs($aca)
            ->get(route('admin.translations.index'))
            ->assertOk()
            ->assertSee(__('hubs.translations'))
            ->assertSee('تاريخ الكنيسة')
            ->assertSee(__('academics.verify_translation'));
    }

    #[Test]
    public function theme_logo_fields_persist(): void
    {
        $this->seed();
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $theme = Theme::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($admin)->put(route('admin.theme.update', $theme), [
            'name' => $theme->name,
            'site_name' => $theme->site_name,
            'logo_light_url' => 'https://cdn.example.com/logo-light.svg',
            'logo_dark_url' => 'https://cdn.example.com/logo-dark.svg',
            'favicon_url' => 'https://cdn.example.com/favicon.ico',
            'is_active' => true,
            'tokens' => [
                'light' => ['primary' => '#5d0326', 'bg1' => '#f8f9ff', 'accent' => '#eac167'],
                'dark' => ['primary' => '#ffb1c0', 'bg1' => '#0d1322', 'accent' => '#e9c16d'],
            ],
        ])->assertRedirect();

        $fresh = $theme->fresh();
        $this->assertSame('https://cdn.example.com/logo-light.svg', $fresh->logo_light_url);
        $this->assertSame('https://cdn.example.com/logo-dark.svg', $fresh->logo_dark_url);
        $this->assertSame('https://cdn.example.com/favicon.ico', $fresh->favicon_url);
        $this->assertSame('#5d0326', $fresh->tokens['light']['primary'] ?? null);
    }

    #[Test]
    public function applications_queue_respects_status_filter(): void
    {
        $this->seed();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create([
            'is_reviewer' => true,
        ]);
        $student = User::factory()->withRole(RoleType::Student)->create();

        $scheme = GradingScheme::query()->firstOrFail();
        $program = Program::query()->create([
            'code' => 'ADM-Q',
            'name' => 'Admissions Queue Program',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'grading_scheme_id' => $scheme->id,
            'active' => true,
        ]);
        $form = ApplicationForm::query()->create([
            'program_id' => $program->id,
            'name' => 'Queue Form',
            'active' => true,
        ]);

        $waitlisted = Application::query()->create([
            'applicant_id' => $student->id,
            'program_id' => $program->id,
            'form_id' => $form->id,
            'status' => ApplicationStatus::Waitlisted,
            'reviewer_id' => $adm->id,
            'submitted_at' => now()->subDay(),
        ]);

        $underReview = Application::query()->create([
            'applicant_id' => User::factory()->withRole(RoleType::Student)->create()->id,
            'program_id' => $program->id,
            'form_id' => $form->id,
            'status' => ApplicationStatus::UnderReview,
            'reviewer_id' => $adm->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($adm)
            ->get(route('admin.applications.index', ['status' => ApplicationStatus::Waitlisted->value]))
            ->assertOk()
            ->assertSee($waitlisted->applicant->email)
            ->assertDontSee($underReview->applicant->email)
            ->assertSee('spims-status-badge', false);
    }
}
