<?php

namespace Tests\Feature\Finance;

use App\Enums\CommunicationChannel;
use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\OfferingMode;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPlanInstallmentStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleType;
use App\Models\CommunicationLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\User;
use App\Services\Finance\GatewayRouter;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    private function pricedOffering(int $usd = 5000, int $egp = 150000, string $code = 'PLAN1'): CourseOffering
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => 'Plan Course '.$code,
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
    public function installments_sum_to_invoice_total_including_remainder_cents(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'EG']);

        $invoice = app(InvoiceService::class)->createManual(
            $fin,
            $student,
            Currency::Egp,
            10001,
            'Remainder lab'
        );

        $this->actingAs($student)
            ->post(route('finance.payment-plan.store', $invoice), [
                'installment_count' => 3,
            ])
            ->assertRedirect();

        $plan = PaymentPlan::query()->where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($plan);
        $this->assertSame(PaymentPlanStatus::Open, $plan->status);

        $amounts = $plan->installments()->orderBy('due_on')->pluck('amount_minor')->all();
        $this->assertSame([3333, 3333, 3335], $amounts);
        $this->assertSame($invoice->total_minor, (int) array_sum($amounts));

        $this->actingAs($student)
            ->get(route('finance.invoices.show', $invoice))
            ->assertOk()
            ->assertSee(__('finance.installment_schedule'), false)
            ->assertSee(__('finance.pay_installment'), false)
            ->assertSee('3335', false);
    }

    #[Test]
    public function student_can_split_egp_invoice_into_three_from_blade(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'EG']);
        $offering = $this->pricedOffering();

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
        ])->assertRedirect();

        $invoice = Invoice::query()->where('student_id', $student->id)->first();
        $this->assertSame(Currency::Egp, $invoice->currency);
        $this->assertSame(150000, $invoice->total_minor);

        $this->actingAs($student)
            ->get(route('finance.invoices.show', $invoice))
            ->assertOk()
            ->assertSee(__('finance.pay_in_n', ['n' => 3]), false)
            ->assertSee(__('finance.create_plan'), false);

        $this->actingAs($student)
            ->post(route('finance.payment-plan.store', $invoice), [
                'installment_count' => 3,
            ])
            ->assertRedirect();

        $plan = PaymentPlan::query()->first();
        $this->assertSame(3, $plan->installment_count);
        $this->assertSame(150000, (int) $plan->installments()->sum('amount_minor'));
        $this->assertSame([50000, 50000, 50000], $plan->installments()->orderBy('due_on')->pluck('amount_minor')->all());

        $this->actingAs($student)
            ->get(route('finance.invoices.show', $invoice))
            ->assertOk()
            ->assertSee(__('finance.pay_remaining'), false)
            ->assertDontSee(__('finance.create_plan'), false);
    }

    #[Test]
    public function bursar_can_attach_a_plan_from_admin_finance(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'EG']);
        $invoice = app(InvoiceService::class)->createManual(
            $fin,
            $student,
            Currency::Egp,
            9000,
            'Bursar plan'
        );

        $this->actingAs($fin)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertSee(__('finance.pay_in_n', ['n' => 3]), false);

        $this->actingAs($fin)
            ->post(route('admin.finance.payment-plan.store', $invoice), [
                'installment_count' => 3,
            ])
            ->assertRedirect();

        $this->assertSame(9000, (int) PaymentPlanInstallment::query()->sum('amount_minor'));

        $this->actingAs($fin)
            ->get(route('admin.finance.invoices.show', $invoice))
            ->assertOk()
            ->assertSee(__('finance.installment_schedule'), false);
    }

    #[Test]
    public function early_payoff_closes_the_plan(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'EG']);
        $invoice = app(InvoiceService::class)->createManual(
            $fin,
            $student,
            Currency::Egp,
            9000,
            'Early payoff'
        );

        $plan = app(PaymentPlanService::class)->create($student, $invoice, 3);
        $this->assertSame(PaymentPlanStatus::Open, $plan->status);

        $this->actingAs($student)
            ->post(route('finance.checkout', $invoice), [
                'wallet_money' => 0,
                'wallet_points' => 0,
                'gateway' => 'PAYMOB',
            ])
            ->assertRedirect(route('finance.index'));

        $plan->refresh();
        $this->assertSame(PaymentPlanStatus::Completed, $plan->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(0, $invoice->fresh()->amountDue());

        $openLeft = $plan->installments()
            ->whereIn('status', [
                PaymentPlanInstallmentStatus::Pending->value,
                PaymentPlanInstallmentStatus::Due->value,
                PaymentPlanInstallmentStatus::Overdue->value,
            ])
            ->count();
        $this->assertSame(0, $openLeft);
        $this->assertTrue(
            $plan->installments->every(
                fn (PaymentPlanInstallment $row) => in_array($row->status, [
                    PaymentPlanInstallmentStatus::Paid,
                    PaymentPlanInstallmentStatus::Cancelled,
                ], true)
            )
        );
    }

    #[Test]
    public function live_charge_is_not_called_in_testing_even_when_keys_exist(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        config([
            'services.payments.mock_auto_complete' => false,
            'services.paypal.client_id' => 'live-client-id',
            'services.paypal.secret' => 'live-client-secret',
            'services.paymob.api_key' => 'live-paymob-key',
            'services.paymob.hmac' => 'live-paymob-hmac',
            'services.cashier.secret' => 'sk_live_not_a_test_default',
        ]);

        $router = app(GatewayRouter::class);

        $paypal = $router->charge(PaymentMethod::Paypal, 5000, Currency::Usd, 'pay-test-1');
        $paymob = $router->charge(PaymentMethod::Paymob, 150000, Currency::Egp, 'pay-test-2');
        $cashier = $router->charge(PaymentMethod::Cashier, 150000, Currency::Egp, 'pay-test-3');

        $this->assertMatchesRegularExpression('/^PAYPAL-[0-9A-HJKMNP-TV-Z]+$/', $paypal);
        $this->assertMatchesRegularExpression('/^PAYMOB-[0-9A-HJKMNP-TV-Z]+$/', $paymob);
        $this->assertMatchesRegularExpression('/^CASHIER-[0-9A-HJKMNP-TV-Z]+$/', $cashier);
        Http::assertNothingSent();
    }

    #[Test]
    public function overdue_installment_sends_one_reminder_and_is_idempotent(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'country_code' => 'EG',
            'notify_email' => true,
        ]);
        $invoice = app(InvoiceService::class)->createManual(
            $fin,
            $student,
            Currency::Egp,
            9000,
            'Dunning'
        );

        app(PaymentPlanService::class)->create($student, $invoice, 3, now()->subDays(2));

        $this->artisan('finance:dunning-overdue-installments')
            ->assertSuccessful()
            ->expectsOutput('Dunning reminders sent: 1');

        $this->assertSame(1, CommunicationLog::query()
            ->where('type', 'finance.installment_overdue')
            ->where('recipient_id', $student->id)
            ->where('channel', CommunicationChannel::Mail)
            ->count());

        $installment = PaymentPlanInstallment::query()->orderBy('due_on')->first();
        $this->assertNotNull($installment->dunning_sent_at);
        $this->assertSame(PaymentPlanInstallmentStatus::Overdue, $installment->status);

        $this->artisan('finance:dunning-overdue-installments')
            ->assertSuccessful()
            ->expectsOutput('Dunning reminders sent: 0');

        $this->assertSame(1, CommunicationLog::query()
            ->where('type', 'finance.installment_overdue')
            ->where('recipient_id', $student->id)
            ->count());
    }

    #[Test]
    public function donation_completes_under_testing_mock_and_stays_pending_when_mock_is_off(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'EG']);

        $this->actingAs($student)->post(route('donate.store'), [
            'currency' => 'EGP',
            'amount_minor' => 1000,
            'designation' => 'scholarship',
        ])->assertRedirect(route('finance.index'));

        $payment = Payment::query()->whereNull('invoice_id')->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::Completed, $payment->status);

        config(['services.payments.mock_auto_complete' => false]);

        $this->actingAs($student)->post(route('donate.store'), [
            'currency' => 'EGP',
            'amount_minor' => 500,
            'designation' => 'chapel',
        ])->assertRedirect(route('finance.index'));

        $pending = Payment::query()->whereNull('invoice_id')->where('amount_minor', 500)->first();
        $this->assertSame(PaymentStatus::Pending, $pending->status);
        $this->assertNotNull($pending->gateway_ref);
    }

    #[Test]
    public function superadmin_scheduled_tasks_list_reminders_and_dunning(): void
    {
        $admin = User::factory()->withRole(RoleType::SuperAdmin)->create();

        $this->actingAs($admin)
            ->get(route('superadmin.scheduled-tasks.index'))
            ->assertOk()
            ->assertSee('communications:fire-reminders', false)
            ->assertSee('finance:dunning-overdue-installments', false);
    }

    #[Test]
    public function other_student_cannot_attach_a_plan(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $owner = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'EG']);
        $other = User::factory()->withRole(RoleType::Student)->create(['country_code' => 'EG']);
        $invoice = app(InvoiceService::class)->createManual(
            $fin,
            $owner,
            Currency::Egp,
            3000,
            'Owned'
        );

        $this->actingAs($other)
            ->post(route('finance.payment-plan.store', $invoice), [
                'installment_count' => 3,
            ])
            ->assertSessionHasErrors('invoice');

        $this->assertSame(0, PaymentPlan::query()->count());
    }
}
