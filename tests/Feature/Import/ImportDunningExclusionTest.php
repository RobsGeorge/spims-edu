<?php

namespace Tests\Feature\Import;

use App\Enums\CommunicationChannel;
use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentPlanInstallmentStatus;
use App\Enums\RoleType;
use App\Models\CommunicationLog;
use App\Models\Invoice;
use App\Models\PaymentPlanInstallment;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Per docs/legacy-data-import-plan.md §8 / §14 Q9: a legacy opening-balance invoice is
 * visible and payable by the student but excluded from *automated* dunning by default.
 * DunnOverdueInstallmentsCommand delegates to PaymentPlanService::dunnOverdue(), which
 * must skip any installment whose invoice carries a non-null source_system.
 */
class ImportDunningExclusionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_legacy_invoice_overdue_by_date_is_never_selected_by_the_dunning_command(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create([
            'country_code' => 'EG',
            'notify_email' => true,
        ]);

        // A legacy opening-balance invoice, exactly as L6 commit would create it.
        $legacyInvoice = Invoice::query()->create([
            'student_id' => $student->id,
            'currency' => Currency::Egp,
            'total_minor' => 41250,
            'status' => InvoiceStatus::Open,
            'due_date' => null,
            'source_system' => 'POPULI',
        ]);

        app(PaymentPlanService::class)->create($student, $legacyInvoice, 3, now()->subDays(2));

        $this->artisan('finance:dunning-overdue-installments')
            ->assertSuccessful()
            ->expectsOutput('Dunning reminders sent: 0');

        $installment = PaymentPlanInstallment::query()
            ->whereHas('plan', fn ($q) => $q->where('invoice_id', $legacyInvoice->id))
            ->orderBy('due_on')
            ->first();

        $this->assertNotNull($installment);
        // markDue() still runs for everyone (it only flips PENDING -> DUE by date and
        // has nothing to do with dunning exclusion), but dunning itself never touched it.
        $this->assertNull($installment->dunning_sent_at);
        $this->assertNotSame(PaymentPlanInstallmentStatus::Overdue, $installment->status);

        $this->assertSame(0, CommunicationLog::query()
            ->where('type', 'finance.installment_overdue')
            ->where('recipient_id', $student->id)
            ->count());
    }

    #[Test]
    public function a_native_invoice_overdue_by_date_is_still_dunned_normally(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'country_code' => 'EG',
            'notify_email' => true,
        ]);

        $invoice = app(InvoiceService::class)->createManual($fin, $student, Currency::Egp, 9000, 'Native tuition');
        $this->assertNull($invoice->source_system);

        app(PaymentPlanService::class)->create($student, $invoice, 3, now()->subDays(2));

        $this->artisan('finance:dunning-overdue-installments')
            ->assertSuccessful()
            ->expectsOutput('Dunning reminders sent: 1');

        $this->assertSame(1, CommunicationLog::query()
            ->where('type', 'finance.installment_overdue')
            ->where('recipient_id', $student->id)
            ->where('channel', CommunicationChannel::Mail)
            ->count());
    }

    #[Test]
    public function a_mixed_run_dunns_only_the_native_invoice(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $legacyStudent = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'EG', 'notify_email' => true]);
        $nativeStudent = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'EG', 'notify_email' => true]);

        $legacyInvoice = Invoice::query()->create([
            'student_id' => $legacyStudent->id,
            'currency' => Currency::Egp,
            'total_minor' => 20000,
            'status' => InvoiceStatus::Open,
            'due_date' => null,
            'source_system' => 'POPULI',
        ]);
        app(PaymentPlanService::class)->create($legacyStudent, $legacyInvoice, 2, now()->subDays(5));

        $nativeInvoice = app(InvoiceService::class)->createManual($fin, $nativeStudent, Currency::Egp, 20000, 'Native tuition');
        app(PaymentPlanService::class)->create($nativeStudent, $nativeInvoice, 2, now()->subDays(5));

        $this->artisan('finance:dunning-overdue-installments')
            ->assertSuccessful()
            ->expectsOutput('Dunning reminders sent: 1');

        $this->assertSame(0, CommunicationLog::query()->where('recipient_id', $legacyStudent->id)->count());
        $this->assertSame(1, CommunicationLog::query()->where('recipient_id', $nativeStudent->id)->count());
    }
}
