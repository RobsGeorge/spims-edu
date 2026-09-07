<?php

namespace App\Services\Finance;

use App\Enums\CommunicationChannel;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentPlanInstallmentStatus;
use App\Enums\PaymentPlanInterval;
use App\Enums\PaymentPlanStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\User;
use App\Services\Communications\ChannelDispatcher;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PaymentPlanService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly ChannelDispatcher $dispatcher,
    ) {}

    public function create(User $actor, Invoice $invoice, int $installmentCount, ?CarbonInterface $startOn = null): PaymentPlan
    {
        $this->authorizeCreate($actor, $invoice);

        if ($installmentCount < 2 || $installmentCount > 12) {
            throw ValidationException::withMessages([
                'installment_count' => [__('finance.plan_count_invalid')],
            ]);
        }

        if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Void, InvoiceStatus::Refunded], true)) {
            throw ValidationException::withMessages(['invoice' => [__('finance.already_paid')]]);
        }

        if ($invoice->amountDue() <= 0 || $invoice->amountPaid() > 0) {
            throw ValidationException::withMessages(['invoice' => [__('finance.plan_requires_unpaid')]]);
        }

        if ($invoice->openPaymentPlan() !== null) {
            throw ValidationException::withMessages(['invoice' => [__('finance.plan_already_open')]]);
        }

        $start = Carbon::parse($startOn ?? now())->startOfDay();

        return $this->audit->withAudit($actor, 'finance.payment_plan_create', function () use ($invoice, $installmentCount, $start) {
            $plan = PaymentPlan::query()->create([
                'invoice_id' => $invoice->id,
                'installment_count' => $installmentCount,
                'interval' => PaymentPlanInterval::Month,
                'start_on' => $start,
                'status' => PaymentPlanStatus::Open,
            ]);

            $this->generateInstallments($plan->fresh(), $invoice);

            return $plan->fresh('installments');
        }, 'PaymentPlan');
    }

    /**
     * Allocate a completed payment across remaining installments and close the
     * plan when the invoice is paid (early payoff).
     */
    public function applyPayment(Invoice $invoice, Payment $payment, ?string $installmentId = null): void
    {
        $plan = $invoice->openPaymentPlan();
        if ($plan === null) {
            return;
        }

        $remaining = $payment->amount_minor;

        if ($installmentId !== null) {
            $targeted = $plan->installments->firstWhere('id', $installmentId);
            if ($targeted instanceof PaymentPlanInstallment && $targeted->isOpen() && $remaining >= $targeted->amount_minor) {
                $targeted->update([
                    'status' => PaymentPlanInstallmentStatus::Paid,
                    'payment_id' => $payment->id,
                ]);
                $remaining -= $targeted->amount_minor;
            }
        }

        foreach ($plan->installments()->whereIn('status', [
            PaymentPlanInstallmentStatus::Pending->value,
            PaymentPlanInstallmentStatus::Due->value,
            PaymentPlanInstallmentStatus::Overdue->value,
        ])->orderBy('due_on')->get() as $installment) {
            if ($remaining <= 0) {
                break;
            }
            if ($remaining < $installment->amount_minor) {
                break;
            }

            $installment->update([
                'status' => PaymentPlanInstallmentStatus::Paid,
                'payment_id' => $payment->id,
            ]);
            $remaining -= $installment->amount_minor;
        }

        $invoice->refresh();
        $plan->refresh();

        if ($invoice->amountDue() === 0 || $invoice->status === InvoiceStatus::Paid) {
            $plan->installments()
                ->whereIn('status', [
                    PaymentPlanInstallmentStatus::Pending->value,
                    PaymentPlanInstallmentStatus::Due->value,
                    PaymentPlanInstallmentStatus::Overdue->value,
                ])
                ->update(['status' => PaymentPlanInstallmentStatus::Cancelled->value]);

            $plan->update(['status' => PaymentPlanStatus::Completed]);

            return;
        }

        $openLeft = $plan->installments()
            ->whereIn('status', [
                PaymentPlanInstallmentStatus::Pending->value,
                PaymentPlanInstallmentStatus::Due->value,
                PaymentPlanInstallmentStatus::Overdue->value,
            ])
            ->exists();

        if (! $openLeft) {
            $plan->update(['status' => PaymentPlanStatus::Completed]);
        }
    }

    /**
     * Mark due/overdue installments and send one S2 reminder per overdue row.
     */
    public function dunnOverdue(?CarbonInterface $today = null): int
    {
        $today = Carbon::parse($today ?? now())->startOfDay();
        $this->markDue($today);

        $overdue = PaymentPlanInstallment::query()
            ->whereIn('status', [
                PaymentPlanInstallmentStatus::Due->value,
                PaymentPlanInstallmentStatus::Overdue->value,
            ])
            ->whereDate('due_on', '<=', $today)
            ->whereNull('dunning_sent_at')
            ->whereNull('payment_id')
            ->whereHas('plan', fn ($q) => $q->where('status', PaymentPlanStatus::Open->value))
            ->with(['plan.invoice.student'])
            ->orderBy('due_on')
            ->get();

        $sent = 0;

        foreach ($overdue as $installment) {
            $updated = PaymentPlanInstallment::query()
                ->where('id', $installment->id)
                ->whereNull('dunning_sent_at')
                ->update([
                    'status' => PaymentPlanInstallmentStatus::Overdue->value,
                    'dunning_sent_at' => now(),
                ]);

            if ($updated !== 1) {
                continue;
            }

            $this->sendDunning($installment->fresh(['plan.invoice.student']));
            $sent++;
        }

        return $sent;
    }

    public function markDue(?CarbonInterface $today = null): int
    {
        $today = Carbon::parse($today ?? now())->startOfDay();

        return PaymentPlanInstallment::query()
            ->where('status', PaymentPlanInstallmentStatus::Pending->value)
            ->whereDate('due_on', '<=', $today)
            ->whereHas('plan', fn ($q) => $q->where('status', PaymentPlanStatus::Open->value))
            ->update(['status' => PaymentPlanInstallmentStatus::Due->value]);
    }

    public function generateInstallments(PaymentPlan $plan, Invoice $invoice): void
    {
        $count = $plan->installment_count;
        $total = $invoice->total_minor;
        $base = intdiv($total, $count);
        $remainder = $total - ($base * $count);
        $start = Carbon::parse($plan->start_on)->startOfDay();
        $today = now()->startOfDay();

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $dueOn = $start->copy()->addMonthsNoOverflow($i);
            $amount = $base + ($i === $count - 1 ? $remainder : 0);
            $status = $dueOn->lte($today)
                ? PaymentPlanInstallmentStatus::Due
                : PaymentPlanInstallmentStatus::Pending;

            $rows[] = PaymentPlanInstallment::query()->create([
                'payment_plan_id' => $plan->id,
                'due_on' => $dueOn,
                'amount_minor' => $amount,
                'currency' => $invoice->currency,
                'status' => $status,
            ]);
        }

        $sum = array_sum(array_map(fn (PaymentPlanInstallment $row) => $row->amount_minor, $rows));
        if ($sum !== $total) {
            throw ValidationException::withMessages([
                'installments' => [__('finance.plan_sum_mismatch')],
            ]);
        }
    }

    private function authorizeCreate(User $actor, Invoice $invoice): void
    {
        if ($invoice->student_id === $actor->id) {
            $this->authorize->authorize($actor, 'finance.pay');

            return;
        }

        if ($this->authorize->allows($actor, 'finance.invoices') || $this->authorize->allows($actor, 'finance.manual')) {
            return;
        }

        if ($this->authorize->allows($actor, 'finance.pay')) {
            throw ValidationException::withMessages(['invoice' => [__('finance.not_owner')]]);
        }

        $this->authorize->authorize($actor, 'finance.invoices');
    }

    private function sendDunning(PaymentPlanInstallment $installment): void
    {
        $student = $installment->plan?->invoice?->student;
        if ($student === null) {
            return;
        }

        $this->dispatcher->dispatch(
            CommunicationChannel::Mail,
            $student,
            'finance.installment_overdue',
            __('finance.dunning_subject'),
            __('finance.dunning_body', [
                'amount' => $installment->amount_minor,
                'currency' => $installment->currency->value,
                'due_on' => $installment->due_on?->toDateString(),
            ]),
            [
                'installment_id' => $installment->id,
                'invoice_id' => $installment->plan?->invoice_id,
                'payment_plan_id' => $installment->payment_plan_id,
            ],
        );
    }
}
