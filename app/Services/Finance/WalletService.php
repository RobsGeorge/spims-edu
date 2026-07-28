<?php

namespace App\Services\Finance;

use App\Enums\Currency;
use App\Enums\LedgerDirection;
use App\Enums\LedgerReason;
use App\Enums\WalletKind;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function ensureWallet(User $user): WalletAccount
    {
        return WalletAccount::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'egp_money_minor' => 0,
                'usd_money_minor' => 0,
                'egp_points_minor' => 0,
                'usd_points_minor' => 0,
            ]
        );
    }

    public function credit(
        User $user,
        Currency $currency,
        WalletKind $kind,
        int $amountMinor,
        LedgerReason $reason,
        ?User $actor = null,
        ?string $paymentId = null,
        ?string $invoiceId = null,
        ?string $note = null,
    ): WalletTransaction {
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => [__('finance.amount_positive')]]);
        }

        return DB::transaction(function () use ($user, $currency, $kind, $amountMinor, $reason, $actor, $paymentId, $invoiceId, $note) {
            $wallet = WalletAccount::query()->where('user_id', $user->id)->lockForUpdate()->first()
                ?? $this->ensureWallet($user);

            $wallet->setBalance($currency, $kind, $wallet->balance($currency, $kind) + $amountMinor);
            $wallet->save();

            $tx = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'currency' => $currency,
                'kind' => $kind,
                'direction' => LedgerDirection::Credit,
                'amount_minor' => $amountMinor,
                'reason' => $reason,
                'related_payment_id' => $paymentId,
                'related_invoice_id' => $invoiceId,
                'note' => $note,
                'created_by_id' => $actor?->id,
            ]);

            $this->audit->write($actor ?? $user, 'wallet.credit', 'WalletTransaction', $tx->id);

            return $tx;
        });
    }

    public function debit(
        User $user,
        Currency $currency,
        WalletKind $kind,
        int $amountMinor,
        LedgerReason $reason,
        ?User $actor = null,
        ?string $paymentId = null,
        ?string $invoiceId = null,
        ?string $note = null,
    ): WalletTransaction {
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => [__('finance.amount_positive')]]);
        }

        return DB::transaction(function () use ($user, $currency, $kind, $amountMinor, $reason, $actor, $paymentId, $invoiceId, $note) {
            $wallet = WalletAccount::query()->where('user_id', $user->id)->lockForUpdate()->first()
                ?? $this->ensureWallet($user);

            $balance = $wallet->balance($currency, $kind);
            if ($balance < $amountMinor) {
                throw ValidationException::withMessages(['wallet' => [__('finance.insufficient_balance')]]);
            }

            $wallet->setBalance($currency, $kind, $balance - $amountMinor);
            $wallet->save();

            $tx = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'currency' => $currency,
                'kind' => $kind,
                'direction' => LedgerDirection::Debit,
                'amount_minor' => $amountMinor,
                'reason' => $reason,
                'related_payment_id' => $paymentId,
                'related_invoice_id' => $invoiceId,
                'note' => $note,
                'created_by_id' => $actor?->id,
            ]);

            $this->audit->write($actor ?? $user, 'wallet.debit', 'WalletTransaction', $tx->id);

            return $tx;
        });
    }

    public function grantPoints(User $actor, User $student, Currency $currency, int $amountMinor, ?string $note = null): WalletTransaction
    {
        $this->authorize->authorize($actor, 'finance.wallet');

        return $this->credit(
            $student,
            $currency,
            WalletKind::Points,
            $amountMinor,
            LedgerReason::AdminGrant,
            $actor,
            note: $note
        );
    }
}
