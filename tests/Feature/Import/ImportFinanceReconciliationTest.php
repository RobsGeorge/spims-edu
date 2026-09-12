<?php

namespace Tests\Feature\Import;

use App\Enums\ImportEntityType;
use App\Enums\RoleType;
use App\Models\ImportLink;
use App\Models\ImportSource;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Import\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The single most important acceptance criterion of L6 (docs/legacy-data-import-plan.md
 * §8): a control-total mismatch of even one minor unit, in any currency, refuses
 * commit outright — with no acknowledge override, unlike the STUDENT entity's
 * row-count mismatch. Enforced at the service layer, so a direct POST to the commit
 * route without ever viewing the dry-run screen is refused exactly the same way.
 */
class ImportFinanceReconciliationTest extends TestCase
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

    private function service(): ImportBatchService
    {
        return app(ImportBatchService::class);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('balances.csv', $content);
    }

    private function linkedStudent(ImportSource $source, string $legacyId): User
    {
        $user = User::factory()->create();

        ImportLink::query()->create([
            'source_id' => $source->id,
            'entity_type' => 'user',
            'legacy_id' => $legacyId,
            'target_type' => User::class,
            'target_id' => $user->id,
        ]);

        return $user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function balanceMapping(): array
    {
        return [
            ['column' => 'LegacyID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Currency', 'target_field' => 'currency', 'transform' => 'upper', 'options' => [], 'ignored' => false],
            ['column' => 'Owed', 'target_field' => 'owed_minor', 'transform' => 'money_to_minor', 'options' => [], 'ignored' => false],
            ['column' => 'Credit', 'target_field' => 'credit_minor', 'transform' => 'money_to_minor', 'options' => [], 'ignored' => false],
        ];
    }

    #[Test]
    public function a_mismatch_of_a_single_minor_unit_refuses_commit_and_writes_nothing(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '1001');

        // The file actually contains 412.50 EGP owed = 41250 minor units.
        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n1001,EGP,412.50,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);

        // The registrar declares one piastre less than the file actually contains.
        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 41249, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);

        $service->dryRun($batch->fresh());

        $this->expectException(RuntimeException::class);

        try {
            $service->commit($actor, $batch->fresh());
        } finally {
            $this->assertSame(0, Invoice::query()->count());
            $this->assertSame(0, WalletTransaction::query()->count());
            $this->assertNotSame('COMMITTED', $batch->fresh()->status->value);
        }
    }

    #[Test]
    public function matching_totals_commit_successfully(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '1002');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n1002,EGP,412.50,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);

        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 41250, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);

        $service->dryRun($batch->fresh());
        $batch = $service->commit($actor, $batch->fresh());

        $this->assertSame('COMMITTED', $batch->status->value);
        $this->assertSame(1, Invoice::query()->count());
    }

    #[Test]
    public function commit_is_refused_even_when_posted_directly_without_ever_viewing_the_dry_run_screen(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '1003');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n1003,EGP,100.00,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);

        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 999999, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);

        // No dry run at all — straight to commit, as if someone bypassed the screen
        // and posted directly to the commit route.
        $this->expectException(RuntimeException::class);

        try {
            $service->commit($actor, $batch->fresh());
        } finally {
            $this->assertSame(0, Invoice::query()->count());
        }
    }

    #[Test]
    public function a_currency_with_real_money_but_no_declared_total_at_all_is_a_mismatch(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '1004');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n1004,USD,50.00,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);

        // The registrar only entered EGP totals — USD was never declared at all, even
        // though this file carries a real USD balance.
        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);

        $service->dryRun($batch->fresh());

        $this->expectException(RuntimeException::class);

        try {
            $service->commit($actor, $batch->fresh());
        } finally {
            $this->assertSame(0, Invoice::query()->count());
        }
    }

    #[Test]
    public function the_dry_run_screen_renders_the_hard_gate_with_no_commit_button_on_mismatch(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '1006');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n1006,EGP,100.00,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);
        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 1, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);
        $service->dryRun($batch->fresh());

        $response = $this->actingAs($actor)->get(route('admin.imports.dry-run', $batch));

        $response->assertOk();
        $response->assertSee(__('import.finance_mismatch_refused'));
        $response->assertSee(__('import.control_totals_mismatch'));
        $response->assertDontSee(__('import.commit_button'));
    }

    #[Test]
    public function the_dry_run_screen_shows_the_commit_button_when_totals_match_exactly(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '1007');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n1007,EGP,100.00,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);
        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 10000, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);
        $service->dryRun($batch->fresh());

        $response = $this->actingAs($actor)->get(route('admin.imports.dry-run', $batch));

        $response->assertOk();
        $response->assertSee(__('import.commit_button'));
        $response->assertDontSee(__('import.finance_mismatch_refused'));
    }

    #[Test]
    public function commit_via_http_is_refused_on_mismatch_with_zero_rows_written(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '1005');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n1005,EGP,100.00,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);
        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 1, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);
        $service->dryRun($batch->fresh());

        // finance.manage is required too, but Super Admin bypasses AuthorizeService
        // entirely — this isolates the money gate itself rather than the permission
        // gate, which has its own dedicated test in ImportFinanceTest.
        $superAdmin = User::factory()->withRole(RoleType::SuperAdmin)->create();

        $response = $this->actingAs($superAdmin)->post(route('admin.imports.commit', $batch));

        $response->assertRedirect();
        $this->assertSame(0, Invoice::query()->count());
        $this->assertNotSame('COMMITTED', $batch->fresh()->status->value);
    }
}
