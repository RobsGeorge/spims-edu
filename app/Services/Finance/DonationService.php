<?php

namespace App\Services\Finance;

use App\Enums\Currency;
use App\Enums\LedgerReason;
use App\Enums\PaymentStatus;
use App\Enums\WalletKind;
use App\Models\Donation;
use App\Models\Payment;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;

class DonationService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly GatewayRouter $gateways,
        private readonly WalletService $wallet,
        private readonly PaymentService $payments,
    ) {}

    public function donate(User $donor, Currency $currency, int $amountMinor, WalletKind $kind = WalletKind::Money, ?string $designation = null): Donation
    {
        $this->authorize->authorize($donor, 'finance.donate');

        return DB::transaction(function () use ($donor, $currency, $amountMinor, $kind, $designation) {
            $method = $this->gateways->methodFor($currency);
            $payment = Payment::query()->create([
                'student_id' => $donor->id,
                'invoice_id' => null,
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'method' => $method,
                'status' => PaymentStatus::Completed,
                'gateway_ref' => $this->gateways->charge($method, $amountMinor, $currency, 'donation'),
                'receipt_serial' => $this->payments->allocateReceiptSerial(),
                'receipt_url' => null,
            ]);

            $donation = Donation::query()->create([
                'user_id' => $donor->id,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'kind' => $kind,
                'designation' => $designation,
                'payment_id' => $payment->id,
            ]);

            $this->audit->write($donor, 'finance.donate', 'Donation', $donation->id);

            return $donation;
        });
    }

    public function topUpWallet(User $actor, User $student, Currency $currency, int $amountMinor, ?string $note = null): void
    {
        $this->authorize->authorize($actor, 'finance.wallet');

        $this->wallet->credit(
            $student,
            $currency,
            WalletKind::Money,
            $amountMinor,
            LedgerReason::Topup,
            $actor,
            note: $note
        );
    }
}
