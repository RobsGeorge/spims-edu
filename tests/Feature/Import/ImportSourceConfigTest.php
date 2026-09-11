<?php

namespace Tests\Feature\Import;

use App\Enums\RoleType;
use App\Models\ImportGradeMapping;
use App\Models\ImportSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportSourceConfigTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
    }

    #[Test]
    public function an_admin_can_create_a_source_and_it_appears_on_the_hub(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.imports.sources.store'), [
            'code' => 'populi',
            'name' => 'Populi',
            'kind' => 'SIS',
            'precedence' => 1,
            'gpa_scale_max' => 4.00,
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
        ])->assertRedirect();

        $source = ImportSource::query()->firstOrFail();
        $this->assertSame('POPULI', $source->code);

        $this->actingAs($admin)->get(route('admin.imports.index'))
            ->assertOk()
            ->assertSee('Populi');
    }

    #[Test]
    public function grade_mappings_can_be_added_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $source = ImportSource::query()->create([
            'code' => 'POPULI', 'name' => 'Populi', 'kind' => 'SIS', 'precedence' => 1,
            'gpa_scale_max' => 4.00, 'default_currency' => 'EGP', 'timezone' => 'UTC', 'active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.imports.sources.grades.store', $source), [
            'legacy_letter' => 'A',
            'spims_letter' => 'A',
            'gpa_points' => 4.0,
            'is_passing' => '1',
            'counts_toward_gpa' => '0',
        ])->assertRedirect();

        $grade = ImportGradeMapping::query()->firstOrFail();
        $this->assertSame('A', $grade->legacy_letter);
        $this->assertFalse($grade->counts_toward_gpa);

        $this->actingAs($admin)->post(route('admin.imports.sources.grades.update', [$source, $grade]), [
            'legacy_letter' => 'A',
            'spims_letter' => 'A+',
            'gpa_points' => 4.0,
            'is_passing' => '1',
        ])->assertRedirect();

        $this->assertSame('A+', $grade->fresh()->spims_letter);

        $this->actingAs($admin)->delete(route('admin.imports.sources.grades.destroy', [$source, $grade]))
            ->assertRedirect();

        $this->assertDatabaseMissing('import_grade_mappings', ['id' => $grade->id]);
    }

    #[Test]
    public function the_sources_screen_renders_with_mappings(): void
    {
        $admin = $this->admin();
        $source = ImportSource::query()->create([
            'code' => 'POPULI', 'name' => 'Populi', 'kind' => 'SIS', 'precedence' => 1,
            'gpa_scale_max' => 4.00, 'default_currency' => 'EGP', 'timezone' => 'UTC', 'active' => true,
        ]);
        ImportGradeMapping::query()->create([
            'source_id' => $source->id, 'legacy_letter' => 'B', 'spims_letter' => 'B',
            'gpa_points' => 3.0, 'is_passing' => true, 'counts_toward_gpa' => false,
        ]);

        $this->actingAs($admin)->get(route('admin.imports.sources.index'))
            ->assertOk()
            ->assertSee('Populi')
            ->assertSee('B');
    }
}
