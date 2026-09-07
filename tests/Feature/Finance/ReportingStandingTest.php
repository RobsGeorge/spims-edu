<?php

namespace Tests\Feature\Finance;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportingStandingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function bursar_can_open_finance_aging_html_and_export_csv(): void
    {
        $bursar = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        Invoice::query()->create([
            'student_id' => $student->id,
            'currency' => Currency::Usd,
            'total_minor' => 5000,
            'status' => InvoiceStatus::Open,
            'due_date' => now()->addWeek(),
        ]);
        Invoice::query()->create([
            'student_id' => $student->id,
            'currency' => Currency::Usd,
            'total_minor' => 2500,
            'status' => InvoiceStatus::Open,
            'due_date' => now()->subDays(20),
        ]);
        $old = Invoice::query()->create([
            'student_id' => $student->id,
            'currency' => Currency::Usd,
            'total_minor' => 1000,
            'status' => InvoiceStatus::Partial,
            'due_date' => now()->subDays(40),
        ]);
        Payment::query()->create([
            'student_id' => $student->id,
            'invoice_id' => $old->id,
            'currency' => Currency::Usd,
            'amount_minor' => 200,
            'method' => PaymentMethod::ManualTransfer,
            'status' => PaymentStatus::Completed,
            'receipt_serial' => 'R-AGE-1',
        ]);

        $this->actingAs($bursar)
            ->get(route('admin.finance.reports'))
            ->assertOk()
            ->assertSee(__('finance.outstanding_by_currency'))
            ->assertSee(__('finance.aging_title'))
            ->assertSee(__('finance.aging_0_14'))
            ->assertSee(__('finance.aging_15_30'))
            ->assertSee(__('finance.aging_31_plus'))
            ->assertSee('USD 50.00', false)
            ->assertSee('USD 25.00', false)
            ->assertSee('USD 8.00', false)
            ->assertSee(__('finance.download_aging_csv'));

        $this->actingAs($bursar)
            ->get(route('admin.reports.finance'))
            ->assertOk()
            ->assertSee(__('reports.finance_title'))
            ->assertSee(__('finance.aging_title'));

        $csv = $this->actingAs($bursar)
            ->get(route('admin.reports.csv', 'finance'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString(__('reports.col_aging_0_14'), $csv);
        $this->assertStringContainsString('USD', $csv);
        $this->assertStringContainsString('8300', $csv);

        $this->assertTrue(
            AuditLog::query()->where('action', 'reports.csv')->where('entity_id', 'finance')->exists()
        );
    }

    #[Test]
    public function academic_admin_can_open_finance_aging_html(): void
    {
        $dean = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        Invoice::query()->create([
            'student_id' => $student->id,
            'currency' => Currency::Usd,
            'total_minor' => 1200,
            'status' => InvoiceStatus::Open,
            'due_date' => now()->subDays(5),
        ]);

        $this->actingAs($dean)
            ->get(route('admin.finance.reports'))
            ->assertOk()
            ->assertSee(__('finance.aging_title'))
            ->assertSee('USD 12.00', false);
    }

    #[Test]
    public function student_cannot_open_finance_aging(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('admin.finance.reports'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.reports.finance'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.reports.csv', 'finance'))->assertForbidden();
    }
}
