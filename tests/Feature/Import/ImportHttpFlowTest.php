<?php

namespace Tests\Feature\Import;

use App\Enums\RoleType;
use App\Models\ImportBatch;
use App\Models\ImportSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proves the wizard actually works end to end through the real routes and controllers
 * — upload, map, validate, dry run, commit, show — not just the service layer.
 */
class ImportHttpFlowTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
    }

    #[Test]
    public function the_whole_wizard_works_end_to_end_through_real_http_requests(): void
    {
        $admin = $this->admin();
        $source = $this->populi();

        $csv = UploadedFile::fake()->createWithContent(
            'roster.csv',
            "ID,Name,Email\n2201,\"Nagy, Sarah\",sarah@example.org\n",
        );

        // Step 1: upload.
        $this->actingAs($admin)
            ->post(route('admin.imports.store'), [
                'source_id' => $source->id,
                'population' => 'ALUMNI',
                'file' => $csv,
            ])
            ->assertRedirect();

        $batch = ImportBatch::query()->firstOrFail();
        $this->assertSame(1, $batch->row_count);

        // Step 2: the mapping screen renders with the auto-suggested mapping.
        $this->actingAs($admin)->get(route('admin.imports.map', $batch))
            ->assertOk()
            ->assertSee(__('import.map_title'))
            ->assertSee('ID');

        // Step 3: submit the mapping — this also runs validate().
        $mapping = collect($batch->fresh()->mapping)->values();
        $columns = $mapping->pluck('column')->all();
        $targetField = $mapping->pluck('target_field')->all();
        $transform = $mapping->pluck('transform')->all();

        // "Name" auto-suggests only first_name; add last_name explicitly so every
        // required field is produced, matching what an admin would do on the screen.
        $columns[] = 'Name';
        $targetField[] = 'last_name';
        $transform[] = 'name_part_last';

        $this->actingAs($admin)
            ->post(route('admin.imports.map.update', $batch), [
                'columns' => $columns,
                'target_field' => $targetField,
                'transform' => $transform,
                'options' => [1 => ['format' => 'last_first'], 3 => ['format' => 'last_first']],
            ])
            ->assertRedirect(route('admin.imports.show', $batch));

        $batch->refresh();
        $this->assertSame('VALIDATED', $batch->status->value);
        $this->assertSame(0, $batch->error_count);

        // Step 4: the show screen offers a dry run.
        $this->actingAs($admin)->get(route('admin.imports.show', $batch))
            ->assertOk()
            ->assertSee(__('import.stat_valid'));

        // Step 5: dry run.
        $this->actingAs($admin)->post(route('admin.imports.dry-run.run', $batch))->assertRedirect();
        $batch->refresh();
        $this->assertSame('DRY_RUN', $batch->status->value);
        $this->assertDatabaseMissing('users', ['email' => 'sarah@example.org']);

        // Step 6: commit.
        $this->actingAs($admin)->post(route('admin.imports.commit', $batch))
            ->assertRedirect(route('admin.imports.show', $batch));

        $batch->refresh();
        $this->assertSame('COMMITTED', $batch->status->value);
        $this->assertDatabaseHas('users', ['email' => 'sarah@example.org', 'status' => 'ARCHIVED']);

        // Step 7: the receipt page shows the rollback control.
        $this->actingAs($admin)->get(route('admin.imports.show', $batch))
            ->assertOk()
            ->assertSee(__('import.rollback_button'));

        // Step 8: rollback.
        $this->actingAs($admin)->post(route('admin.imports.rollback', $batch))
            ->assertRedirect(route('admin.imports.show', $batch));

        $this->assertDatabaseMissing('users', ['email' => 'sarah@example.org']);
        $this->assertSame('ROLLED_BACK', $batch->fresh()->status->value);
    }

    #[Test]
    public function uploading_a_currently_studying_batch_requires_users_manage_in_addition_to_import_stage(): void
    {
        // Academic Admin holds import.stage but not users.manage — can stage an ALUMNI
        // batch but must be refused for an ACTIVE one, since that creates logins.
        $academicAdmin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $source = $this->populi();
        $csv = UploadedFile::fake()->createWithContent('roster.csv', "ID,Name,Email\n1,\"A, B\",a@example.org\n");

        $this->actingAs($academicAdmin)
            ->post(route('admin.imports.store'), ['source_id' => $source->id, 'population' => 'ACTIVE', 'file' => $csv])
            ->assertForbidden();

        $csv2 = UploadedFile::fake()->createWithContent('roster2.csv', "ID,Name,Email\n1,\"A, B\",\n");
        $this->actingAs($academicAdmin)
            ->post(route('admin.imports.store'), ['source_id' => $source->id, 'population' => 'ALUMNI', 'file' => $csv2])
            ->assertRedirect();
    }
}
