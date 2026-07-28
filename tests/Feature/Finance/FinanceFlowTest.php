<?php

namespace Tests\Feature\Finance;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\OfferingMode;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\RoleType;
use App\Enums\WalletKind;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceFlowTest extends TestCase
{
    use RefreshDatabase;

    private function pricedOffering(int $usd = 5000, int $egp = 150000, string $code = 'FIN1'): CourseOffering
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => 'Finance Course '.$code,
            'credit_hours' => 3,
            'default_price_usd' => $usd,
            'default_price_egp' => $egp,
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
    public function enrollment_creates_invoice_and_unpaid_blocks_next_registration(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'US']);
        $offering = $this->pricedOffering();
        $other = $this->pricedOffering(6000, 160000, 'FIN2');

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
        ])->assertRedirect();

        $invoice = Invoice::query()->where('student_id', $student->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame(5000, $invoice->total_minor);
        $this->assertSame(Currency::Usd, $invoice->currency);

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $other->id,
        ])->assertSessionHasErrors('enrollment');
    }

    #[Test]
    public function gateway_checkout_split_wallet_and_receipt_serial(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'US']);
        $offering = $this->pricedOffering(10000);

        $this->actingAs($student)->post(route('enrollments.store'), ['offering_id' => $offering->id]);
        $invoice = Invoice::query()->first();

        app(WalletService::class)->grantPoints($fin, $student, Currency::Usd, 2000);
        app(WalletService::class)->credit($student, Currency::Usd, WalletKind::Money, 3000, \App\Enums\LedgerReason::Topup, $fin);

        $this->actingAs($student)->post(route('finance.checkout', $invoice), [
            'wallet_money' => 3000,
            'wallet_points' => 2000,
            'gateway' => 'PAYPAL',
        ])->assertRedirect(route('finance.index'));

        $payment = Payment::query()->first();
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertNotNull($payment->receipt_serial);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $wallet = WalletAccount::query()->where('user_id', $student->id)->first();
        $this->assertSame(0, $wallet->usd_money_minor);
        $this->assertSame(0, $wallet->usd_points_minor);
    }

    #[Test]
    public function manual_payment_verify_issues_receipt_and_egp_uses_paymob(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'EG']);
        $offering = $this->pricedOffering();

        $this->actingAs($student)->post(route('enrollments.store'), ['offering_id' => $offering->id]);
        $invoice = Invoice::query()->first();
        $this->assertSame(Currency::Egp, $invoice->currency);

        $this->actingAs($fin)->post(route('admin.finance.manual', $invoice), [
            'method' => PaymentMethod::ManualTransfer->value,
            'amount_minor' => $invoice->total_minor,
        ])->assertRedirect();

        $payment = Payment::query()->first();
        $this->assertSame(PaymentStatus::PendingVerification, $payment->status);

        $this->actingAs($fin)->post(route('admin.finance.verify', $payment))->assertRedirect();
        $payment->refresh();
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertMatchesRegularExpression('/^SPIMS-\d{4}-\d{5}$/', $payment->receipt_serial);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    #[Test]
    public function drop_refunds_to_wallet_and_webhook_is_idempotent(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'US']);
        $offering = $this->pricedOffering(8000);

        $this->actingAs($student)->post(route('enrollments.store'), ['offering_id' => $offering->id]);
        $invoice = Invoice::query()->first();

        $this->actingAs($student)->post(route('finance.checkout', $invoice), [
            'wallet_money' => 0,
            'wallet_points' => 0,
            'gateway' => 'PAYPAL',
        ])->assertRedirect();

        $enrollment = Enrollment::query()->first();
        $this->actingAs($student)->post(route('enrollments.drop', $enrollment))->assertRedirect();

        $wallet = WalletAccount::query()->where('user_id', $student->id)->first();
        $this->assertSame(8000, $wallet->usd_money_minor);
        $this->assertSame(InvoiceStatus::Refunded, $invoice->fresh()->status);

        // Webhook idempotency on already-completed payment
        $pending = Payment::query()->create([
            'student_id' => $student->id,
            'invoice_id' => null,
            'currency' => Currency::Usd,
            'amount_minor' => 100,
            'method' => PaymentMethod::Paypal,
            'status' => PaymentStatus::Pending,
            'gateway_ref' => 'PAYPAL-TEST-REF',
        ]);

        $payload = ['id' => 'evt-1'];
        $signature = hash_hmac('sha256', json_encode($payload), config('services.paypal.webhook_id'));

        $this->postJson(route('api.webhooks.payments'), [
            'method' => 'PAYPAL',
            'gateway_ref' => 'PAYPAL-TEST-REF',
            'signature' => $signature,
            'payload' => $payload,
        ])->assertOk()->assertJsonPath('status', 'COMPLETED');

        $this->postJson(route('api.webhooks.payments'), [
            'method' => 'PAYPAL',
            'gateway_ref' => 'PAYPAL-TEST-REF',
            'signature' => $signature,
            'payload' => $payload,
        ])->assertOk();

        $this->assertSame(1, Payment::query()->where('gateway_ref', 'PAYPAL-TEST-REF')->where('status', PaymentStatus::Completed)->count());
        $this->assertNotNull($pending->fresh()->receipt_serial);
    }

    #[Test]
    public function refund_request_approval_credits_original_currency_points_option(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'US']);
        $offering = $this->pricedOffering(4000);

        $this->actingAs($student)->post(route('enrollments.store'), ['offering_id' => $offering->id]);
        $invoice = Invoice::query()->first();
        $this->actingAs($student)->post(route('finance.checkout', $invoice))->assertRedirect();

        $payment = Payment::query()->first();
        $refund = app(PaymentService::class)->requestRefund($student, $payment, 1500, true, 'partial');
        $this->assertSame(RefundStatus::Requested, $refund->status);

        $this->actingAs($fin)->post(route('admin.finance.refunds.approve', $refund))->assertRedirect();
        $wallet = WalletAccount::query()->where('user_id', $student->id)->first();
        $this->assertSame(1500, $wallet->usd_points_minor);
    }

    #[Test]
    public function egypt_student_gets_egp_invoice_and_donation_works(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'EG']);
        $invoice = app(InvoiceService::class)->createManual(
            User::factory()->withRole(RoleType::FinancialAdmin)->create(),
            $student,
            Currency::Egp,
            25000,
            'Lab fee'
        );
        $this->assertSame(Currency::Egp, $invoice->currency);

        $this->actingAs($student)->post(route('donate.store'), [
            'currency' => 'EGP',
            'amount_minor' => 1000,
            'designation' => 'scholarship',
        ])->assertRedirect(route('finance.index'));

        $this->assertDatabaseHas('donations', [
            'user_id' => $student->id,
            'amount_minor' => 1000,
            'currency' => 'EGP',
        ]);
    }
}
