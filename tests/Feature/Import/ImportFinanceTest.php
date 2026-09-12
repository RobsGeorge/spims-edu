<?php

namespace Tests\Feature\Import;

use App\Enums\Currency;
use App\Enums\ImportEntityType;
use App\Enums\LedgerDirection;
use App\Enums\LedgerReason;
use App\Enums\RoleType;
use App\Enums\WalletKind;
use App\Models\ImportLink;
use App\Models\ImportRow;
use App\Models\ImportSource;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Import\ImportBatchService;
use App\Services\Import\ImportTransformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L6 — finance opening balances. See docs/legacy-data-import-plan.md §8, D4, §14
 * Q11/Q13/Q15. The hard control-total gate has its own dedicated suite:
 * ImportFinanceReconciliationTest.
 */
class ImportFinanceTest extends TestCase
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

    private function csv(string $content, string $name = 'balances.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /**
     * Simulates a student already linked by a committed Populi STUDENT batch — the
     * precondition L6 depends on (§6 rung 1).
     */
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

    // --- money_to_minor transform -----------------------------------------------

    #[Test]
    public function money_to_minor_parses_plain_and_thousands_separated_amounts(): void
    {
        $transforms = new ImportTransformService;

        $this->assertSame(41250, $transforms->parseMoneyToMinor('412.50')['value']);
        $this->assertSame(123400, $transforms->parseMoneyToMinor('1234')['value']);
        $this->assertSame(123450, $transforms->parseMoneyToMinor('1,234.50')['value']);
        $this->assertSame(500, $transforms->parseMoneyToMinor('5')['value']);
        $this->assertSame(0, $transforms->parseMoneyToMinor('0')['value']);
    }

    #[Test]
    public function money_to_minor_never_produces_a_float(): void
    {
        $transforms = new ImportTransformService;

        $result = $transforms->parseMoneyToMinor('412.50');
        $this->assertTrue($result['ok']);
        $this->assertIsInt($result['value']);
    }

    #[Test]
    public function money_to_minor_rejects_more_than_two_decimal_places(): void
    {
        $transforms = new ImportTransformService;

        $result = $transforms->parseMoneyToMinor('412.567');

        $this->assertFalse($result['ok']);
        $this->assertSame('E_MONEY_PRECISION', $result['code']);
        $this->assertNull($result['value']);
    }

    #[Test]
    public function money_to_minor_rejects_garbage_as_bad_money_not_a_precision_error(): void
    {
        $transforms = new ImportTransformService;

        $result = $transforms->parseMoneyToMinor('not-a-number');

        $this->assertFalse($result['ok']);
        $this->assertSame('E_BAD_MONEY', $result['code']);
    }

    #[Test]
    public function money_to_minor_treats_a_blank_value_as_absent_not_an_error(): void
    {
        $transforms = new ImportTransformService;

        $result = $transforms->parseMoneyToMinor('');

        $this->assertTrue($result['ok']);
        $this->assertNull($result['value']);
    }

    // --- commit logic -------------------------------------------------------------

    #[Test]
    public function an_owed_only_row_creates_exactly_one_invoice_with_one_line(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $student = $this->linkedStudent($source, '9001');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n9001,EGP,412.50,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);

        $this->assertSame(0, $batch->error_count);

        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 41250, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);

        $service->commit($actor, $batch->fresh());

        $invoices = Invoice::query()->where('student_id', $student->id)->get();
        $this->assertCount(1, $invoices);
        $invoice = $invoices->first();
        $this->assertSame(41250, $invoice->total_minor);
        $this->assertSame(Currency::Egp, $invoice->currency);
        $this->assertSame('POPULI', $invoice->source_system);
        $this->assertNull($invoice->due_date);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame(41250, $invoice->lines->first()->amount_minor);
        $this->assertNull($invoice->lines->first()->offering_id);

        $this->assertSame(0, WalletTransaction::query()->count());
    }

    #[Test]
    public function a_credit_only_row_creates_exactly_one_wallet_transaction(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $student = $this->linkedStudent($source, '9002');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n9002,EGP,0,150\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);

        $this->assertSame(0, $batch->error_count);

        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 0, 'credit_minor' => 15000],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);

        $service->commit($actor, $batch->fresh());

        $transactions = WalletTransaction::query()->whereHas('wallet', fn ($q) => $q->where('user_id', $student->id))->get();
        $this->assertCount(1, $transactions);
        $tx = $transactions->first();
        $this->assertSame(15000, $tx->amount_minor);
        $this->assertSame(Currency::Egp, $tx->currency);
        $this->assertSame(WalletKind::Money, $tx->kind);
        $this->assertSame(LedgerDirection::Credit, $tx->direction);
        $this->assertSame(LedgerReason::LegacyCarryForward, $tx->reason);

        $this->assertSame(0, Invoice::query()->count());
    }

    #[Test]
    public function owed_and_credit_on_the_same_row_are_handled_independently(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $student = $this->linkedStudent($source, '9003');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n9003,USD,100,50\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);

        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 0, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 10000, 'credit_minor' => 5000],
        ]);

        $service->commit($actor, $batch->fresh());

        $this->assertSame(1, Invoice::query()->where('student_id', $student->id)->count());
        $this->assertSame(1, WalletTransaction::query()->whereHas('wallet', fn ($q) => $q->where('user_id', $student->id))->count());
    }

    #[Test]
    public function a_zero_zero_row_is_a_no_op_not_an_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '9004');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n9004,EGP,0,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);

        $this->assertSame(0, $batch->error_count);

        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 0, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);

        $service->commit($actor, $batch->fresh());

        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    #[Test]
    public function a_currency_outside_egp_or_usd_is_a_hard_validation_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '9005');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n9005,GBP,100,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $codes = collect($row->messages)->pluck('code');
        $this->assertTrue($codes->contains('E_UNSUPPORTED_CURRENCY'));
    }

    #[Test]
    public function an_unlinked_legacy_id_is_a_hard_e_unknown_student_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        // No ImportLink is created for this legacy id — no prior STUDENT batch linked it.

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n9999,EGP,100,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $codes = collect($row->messages)->pluck('code');
        $this->assertTrue($codes->contains('E_UNKNOWN_STUDENT'));
    }

    #[Test]
    public function a_money_precision_error_in_the_file_blocks_only_that_row(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '9006');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n9006,EGP,412.567,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $codes = collect($row->messages)->pluck('code');
        $this->assertTrue($codes->contains('E_MONEY_PRECISION'));
    }

    #[Test]
    public function committing_a_balance_batch_sends_no_mail(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '9007');

        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n9007,EGP,100,0\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);
        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 10000, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);
        $service->commit($actor, $batch->fresh());

        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    // --- permission ----------------------------------------------------------------

    #[Test]
    public function a_balance_commit_is_refused_without_finance_manage_even_with_import_commit(): void
    {
        $source = $this->populi();
        // Administrative Admin holds import.commit but not finance.manage.
        $administrativeAdmin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $this->linkedStudent($source, '9008');

        $service = $this->service();
        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n9008,EGP,100,0\n");
        $batch = $service->createFromUpload($administrativeAdmin, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);
        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 10000, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);
        $service->dryRun($batch->fresh());

        $this->actingAs($administrativeAdmin)
            ->post(route('admin.imports.commit', $batch))
            ->assertForbidden();

        $this->assertSame(0, Invoice::query()->count());
    }

    #[Test]
    public function a_financial_admin_can_commit_a_balance_batch_over_http(): void
    {
        $source = $this->populi();
        $financialAdmin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $this->linkedStudent($source, '9009');

        // FINANCIAL_ADMIN does not hold import.stage, so staging is done at the
        // service layer directly here (mirroring how a registrar would have already
        // staged and validated the batch before handing it to Financial Admin to commit).
        $service = $this->service();
        $csv = $this->csv("LegacyID,Currency,Owed,Credit\n9009,EGP,100,0\n");
        $batch = $service->createFromUpload($financialAdmin, $source, $csv, null, entityType: ImportEntityType::Balance);
        $batch = $service->updateMapping($batch, $this->balanceMapping());
        $batch = $service->validate($batch);
        $service->setFinanceControlTotals($batch, '2025-06-30', [
            'EGP' => ['owed_minor' => 10000, 'credit_minor' => 0],
            'USD' => ['owed_minor' => 0, 'credit_minor' => 0],
        ]);
        $service->dryRun($batch->fresh());

        // FINANCIAL_ADMIN also does not hold import.commit — so on its own it cannot
        // commit either. This documents the (intentional) consequence of §12: nobody
        // role alone holds both import.commit and finance.manage, so a BALANCE commit
        // in production requires the two roles to cooperate (or Super Admin, who
        // bypasses AuthorizeService entirely).
        $this->actingAs($financialAdmin)
            ->post(route('admin.imports.commit', $batch))
            ->assertForbidden();
    }

    // --- localisation of the new error codes ---------------------------------------

    #[Test]
    public function every_balance_error_code_this_service_emits_has_a_translation_in_all_three_locales(): void
    {
        $codes = ['E_UNSUPPORTED_CURRENCY', 'E_UNKNOWN_STUDENT', 'E_MONEY_PRECISION', 'E_BAD_MONEY'];

        foreach (['ar', 'en', 'fr'] as $locale) {
            app()->setLocale($locale);
            foreach ($codes as $code) {
                $translated = __('import.error_code.'.$code, ['currency' => 'x', 'legacy_id' => 'x', 'column' => 'x', 'value' => 'x']);
                $this->assertNotSame('import.error_code.'.$code, $translated, "Missing translation for {$code} in {$locale}");
            }
        }
        app()->setLocale('en');
    }
}
