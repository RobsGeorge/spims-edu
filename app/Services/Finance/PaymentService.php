<?php

namespace App\Services\Finance;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\LedgerReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\WalletKind;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly WalletService $wallet,
        private readonly InvoiceService $invoices,
        private readonly GatewayRouter $gateways,
    ) {}

    /**
     * @param  array{wallet_money?: int, wallet_points?: int, gateway?: string}  $split
     */
    public function checkout(User $student, Invoice $invoice, array $split = []): Payment
    {
        $this->authorize->authorize($student, 'finance.pay');

        if ($invoice->student_id !== $student->id) {
            throw ValidationException::withMessages(['invoice' => [__('finance.not_owner')]]);
        }

        if ($invoice->status === InvoiceStatus::Paid) {
            throw ValidationException::withMessages(['invoice' => [__('finance.already_paid')]]);
        }

        $due = $invoice->amountDue();
        if ($due <= 0) {
            throw ValidationException::withMessages(['invoice' => [__('finance.nothing_due')]]);
        }

        $walletMoney = (int) ($split['wallet_money'] ?? 0);
        $walletPoints = (int) ($split['wallet_points'] ?? 0);
        $gatewayPortion = $due - $walletMoney - $walletPoints;

        if ($walletMoney < 0 || $walletPoints < 0 || $gatewayPortion < 0) {
            throw ValidationException::withMessages(['split' => [__('finance.invalid_split')]]);
        }

        return DB::transaction(function () use ($student, $invoice, $due, $walletMoney, $walletPoints, $gatewayPortion, $split) {
            $method = $this->resolvePrimaryMethod($invoice->currency, $walletMoney, $walletPoints, $gatewayPortion, $split['gateway'] ?? null);

            $payment = Payment::query()->create([
                'student_id' => $student->id,
                'invoice_id' => $invoice->id,
                'currency' => $invoice->currency,
                'amount_minor' => $due,
                'method' => $method,
                'status' => PaymentStatus::Pending,
            ]);

            if ($walletMoney > 0) {
                $this->wallet->debit($student, $invoice->currency, WalletKind::Money, $walletMoney, LedgerReason::Payment, $student, $payment->id, $invoice->id);
            }
            if ($walletPoints > 0) {
                $this->wallet->debit($student, $invoice->currency, WalletKind::Points, $walletPoints, LedgerReason::Payment, $student, $payment->id, $invoice->id);
            }

            if ($gatewayPortion > 0) {
                $ref = $this->gateways->charge($method, $gatewayPortion, $invoice->currency, $payment->id);
                $payment->update(['gateway_ref' => $ref, 'method' => $method]);

                if (config('services.payments.mock_auto_complete', true)) {
                    $payment->update(['status' => PaymentStatus::Completed]);
                    $this->finalizeCompletedPayment($payment->fresh());
                }
            } else {
                $payment->update(['status' => PaymentStatus::Completed]);
                $this->finalizeCompletedPayment($payment->fresh());
            }

            return $payment->fresh();
        });
    }

    public function recordManual(User $actor, Invoice $invoice, PaymentMethod $method, int $amountMinor, ?string $proofUrl = null, ?string $reference = null): Payment
    {
        $this->authorize->authorize($actor, 'finance.manual');

        if (! in_array($method, [PaymentMethod::ManualCash, PaymentMethod::ManualTransfer, PaymentMethod::ManualCheque], true)) {
            throw ValidationException::withMessages(['method' => [__('finance.manual_method_only')]]);
        }

        $payment = Payment::query()->create([
            'student_id' => $invoice->student_id,
            'invoice_id' => $invoice->id,
            'currency' => $invoice->currency,
            'amount_minor' => $amountMinor,
            'method' => $method,
            'status' => PaymentStatus::PendingVerification,
            'proof_url' => $proofUrl,
            'gateway_ref' => $reference,
            'recorded_by_id' => $actor->id,
        ]);

        $this->audit->write($actor, 'finance.manual_record', 'Payment', $payment->id);

        return $payment;
    }

    public function verifyManual(User $actor, Payment $payment): Payment
    {
        $this->authorize->authorize($actor, 'finance.manual');

        if ($payment->status !== PaymentStatus::PendingVerification) {
            throw ValidationException::withMessages(['payment' => [__('finance.not_pending_verification')]]);
        }

        return DB::transaction(function () use ($actor, $payment) {
            $payment->update([
                'status' => PaymentStatus::Completed,
                'verified_by_id' => $actor->id,
            ]);

            $this->finalizeCompletedPayment($payment->fresh());
            $this->audit->write($actor, 'finance.manual_verify', 'Payment', $payment->id);

            return $payment->fresh();
        });
    }

    public function handleWebhook(PaymentMethod $method, string $gatewayRef, string $signature, array $payload): Payment
    {
        if (! $this->gateways->verifySignature($method, $signature, $payload)) {
            throw ValidationException::withMessages(['webhook' => [__('finance.invalid_signature')]]);
        }

        return DB::transaction(function () use ($gatewayRef) {
            $existing = Payment::query()->where('gateway_ref', $gatewayRef)->lockForUpdate()->first();

            if ($existing === null) {
                throw ValidationException::withMessages(['webhook' => [__('finance.unknown_payment')]]);
            }

            if ($existing->status === PaymentStatus::Completed) {
                return $existing; // idempotent
            }

            $existing->update(['status' => PaymentStatus::Completed]);
            $this->finalizeCompletedPayment($existing->fresh());

            return $existing->fresh();
        });
    }

    public function requestRefund(User $actor, Payment $payment, int $amountMinor, bool $asPoints = false, ?string $reason = null): Refund
    {
        if ($payment->student_id !== $actor->id && ! $actor->isSuperAdmin()) {
            $this->authorize->authorize($actor, 'finance.refunds');
        }

        return Refund::query()->create([
            'payment_id' => $payment->id,
            'student_id' => $payment->student_id,
            'amount_minor' => $amountMinor,
            'currency' => $payment->currency,
            'as_points' => $asPoints,
            'status' => RefundStatus::Requested,
            'reason' => $reason,
            'requested_by_id' => $actor->id,
        ]);
    }

    public function approveRefund(User $actor, Refund $refund): Refund
    {
        $this->authorize->authorize($actor, 'finance.refunds');

        if ($refund->status === RefundStatus::Completed) {
            return $refund;
        }

        return DB::transaction(function () use ($actor, $refund) {
            $student = User::query()->findOrFail($refund->student_id);
            $kind = $refund->as_points ? WalletKind::Points : WalletKind::Money;

            $this->wallet->credit(
                $student,
                $refund->currency,
                $kind,
                $refund->amount_minor,
                LedgerReason::Refund,
                $actor,
                $refund->payment_id,
                note: $refund->reason
            );

            $refund->update([
                'status' => RefundStatus::Completed,
                'approved_by_id' => $actor->id,
            ]);

            if ($refund->payment_id) {
                Payment::query()->where('id', $refund->payment_id)->update(['status' => PaymentStatus::Refunded]);
            }

            $this->audit->write($actor, 'finance.refund_approve', 'Refund', $refund->id);

            return $refund->fresh();
        });
    }

    /**
     * Auto-refund enrollment payments into wallet money (drop = 100%, withdraw = semester %).
     */
    public function refundEnrollment(User $actor, Enrollment $enrollment, int $percent, string $reason): ?Refund
    {
        if ($percent <= 0) {
            return null;
        }

        $invoice = Invoice::query()->where('enrollment_id', $enrollment->id)->first();
        if ($invoice === null) {
            return null;
        }

        $paid = $invoice->amountPaid();
        if ($paid <= 0) {
            if ($invoice->status !== InvoiceStatus::Paid) {
                $invoice->update(['status' => InvoiceStatus::Void]);
            }

            return null;
        }

        $amount = (int) floor($paid * min(100, $percent) / 100);
        if ($amount <= 0) {
            return null;
        }

        $payment = $invoice->payments()->where('status', PaymentStatus::Completed)->latest('created_at')->first();

        return DB::transaction(function () use ($actor, $enrollment, $invoice, $payment, $amount, $reason) {
            $refund = Refund::query()->create([
                'payment_id' => $payment?->id,
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'amount_minor' => $amount,
                'currency' => $invoice->currency,
                'as_points' => false,
                'status' => RefundStatus::Completed,
                'reason' => $reason,
                'requested_by_id' => $actor->id,
                'approved_by_id' => $actor->id,
            ]);

            $this->wallet->credit(
                User::query()->findOrFail($enrollment->student_id),
                $invoice->currency,
                WalletKind::Money,
                $amount,
                LedgerReason::Refund,
                $actor,
                $payment?->id,
                $invoice->id,
                $reason
            );

            if ($payment) {
                $payment->update(['status' => PaymentStatus::Refunded]);
            }

            $this->invoices->markRefunded($invoice);
            $this->audit->write($actor, 'finance.enrollment_refund', 'Refund', $refund->id, null, [
                'percent_reason' => $reason,
                'amount_minor' => $amount,
            ]);

            return $refund;
        });
    }

    private function finalizeCompletedPayment(Payment $payment): void
    {
        if ($payment->receipt_serial === null) {
            $payment->update([
                'receipt_serial' => $this->allocateReceiptSerial(),
                'receipt_url' => 'receipts/'.$payment->id.'.html',
            ]);
        }

        if ($payment->invoice_id) {
            $invoice = Invoice::query()->find($payment->invoice_id);
            if ($invoice) {
                $this->invoices->refreshStatus($invoice);
            }
        }

        $this->audit->write(
            User::query()->find($payment->student_id),
            'finance.payment_completed',
            'Payment',
            $payment->id
        );
    }

    public function allocateReceiptSerial(): string
    {
        $year = now()->format('Y');
        $setting = Setting::query()->lockForUpdate()->find('finance.receipt_counter');
        if ($setting === null) {
            $setting = new Setting(['key' => 'finance.receipt_counter']);
        }

        $value = $setting->value ?? [];
        $counters = $value['years'] ?? [];
        $next = ((int) ($counters[$year] ?? 0)) + 1;
        $counters[$year] = $next;
        $setting->value = ['years' => $counters];
        $setting->save();

        return sprintf('SPIMS-%s-%05d', $year, $next);
    }

    private function resolvePrimaryMethod(Currency $currency, int $walletMoney, int $walletPoints, int $gatewayPortion, ?string $gateway): PaymentMethod
    {
        if ($gatewayPortion > 0) {
            return $this->gateways->methodFor($currency, $gateway);
        }
        if ($walletPoints > 0 && $walletMoney === 0) {
            return PaymentMethod::WalletPoints;
        }

        return PaymentMethod::WalletMoney;
    }
}
