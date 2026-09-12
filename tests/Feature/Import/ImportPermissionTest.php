<?php

namespace Tests\Feature\Import;

use App\Enums\RoleType;
use App\Models\ImportSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function populi(): ImportSource
    {
        return ImportSource::query()->create([
            'code' => 'POPULI',
            'name' => 'Populi',
            'kind' => 'SIS',
            'precedence' => 1,
            'gpa_scale_max' => 4.00,
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'active' => true,
        ]);
    }

    #[Test]
    public function administrative_admin_can_view_and_configure_and_stage(): void
    {
        $user = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($user)->get(route('admin.imports.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.imports.sources.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.imports.create'))->assertOk();
    }

    #[Test]
    public function academic_admin_can_view_and_stage_but_not_configure_sources(): void
    {
        $user = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $this->actingAs($user)->get(route('admin.imports.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.imports.create'))->assertOk();

        $this->actingAs($user)
            ->post(route('admin.imports.sources.store'), [
                'code' => 'DENIED', 'name' => 'X', 'kind' => 'SIS', 'precedence' => 1,
                'gpa_scale_max' => 4, 'default_currency' => 'EGP', 'timezone' => 'UTC',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function financial_admin_can_view_but_not_stage_or_configure(): void
    {
        $user = User::factory()->withRole(RoleType::FinancialAdmin)->create();

        $this->actingAs($user)->get(route('admin.imports.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.imports.create'))->assertForbidden();
    }

    #[Test]
    public function instructor_has_no_access_at_all(): void
    {
        $user = User::factory()->withRole(RoleType::Instructor)->create();

        $this->actingAs($user)->get(route('admin.imports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.imports.create'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.imports.sources.index'))->assertForbidden();
    }

    #[Test]
    public function student_has_no_access_at_all(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($user)->get(route('admin.imports.index'))->assertForbidden();
    }

    #[Test]
    public function only_administrative_admin_can_commit(): void
    {
        $source = $this->populi();
        $academicAdmin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $administrativeAdmin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $batch = app(\App\Services\Import\ImportBatchService::class)->createFromUpload(
            $administrativeAdmin,
            $source,
            \Illuminate\Http\UploadedFile::fake()->createWithContent('r.csv', "ID,Name,Email\n1,\"A, B\",a@example.org\n"),
            \App\Enums\ImportPopulation::Alumni,
        );

        $this->actingAs($academicAdmin)
            ->post(route('admin.imports.commit', $batch))
            ->assertForbidden();
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.imports.index'))->assertRedirect(route('auth.login'));
    }

    #[Test]
    public function only_administrative_admin_can_view_the_merge_queue(): void
    {
        $administrativeAdmin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $academicAdmin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $financialAdmin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();

        $this->actingAs($administrativeAdmin)->get(route('admin.imports.merges'))->assertOk();
        $this->actingAs($academicAdmin)->get(route('admin.imports.merges'))->assertForbidden();
        $this->actingAs($financialAdmin)->get(route('admin.imports.merges'))->assertForbidden();
        $this->actingAs($instructor)->get(route('admin.imports.merges'))->assertForbidden();
    }

    #[Test]
    public function only_administrative_admin_can_resolve_a_merge_candidate(): void
    {
        $source = $this->populi();
        $administrativeAdmin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $academicAdmin = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $candidate = \App\Models\ImportMergeCandidate::query()->create([
            'source_id' => $source->id,
            'legacy_id' => 'LEG-1',
            'batch_id' => app(\App\Services\Import\ImportBatchService::class)->createFromUpload(
                $administrativeAdmin,
                $source,
                \Illuminate\Http\UploadedFile::fake()->createWithContent('r.csv', "ID,Name,Email\n1,\"A, B\",a@example.org\n"),
                \App\Enums\ImportPopulation::Alumni,
            )->id,
            'candidate_user_id' => null,
            'score' => 0,
            'matched_on' => [],
            'status' => 'PENDING',
        ]);

        $this->actingAs($academicAdmin)
            ->post(route('admin.imports.merges.resolve', $candidate), ['decision' => 'skip'])
            ->assertForbidden();

        $this->actingAs($administrativeAdmin)
            ->post(route('admin.imports.merges.resolve', $candidate), ['decision' => 'skip'])
            ->assertRedirect(route('admin.imports.merges'));
    }
}
