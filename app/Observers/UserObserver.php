<?php

namespace App\Observers;

use App\Enums\ImportAccountClaimStatus;
use App\Models\ImportAccountClaim;
use App\Models\User;
use App\Support\AuditLogWriter;

/**
 * L5 — flips a legacy account-claim row to CLAIMED the moment a user actually sets a
 * password, without touching SetPasswordController (sensitive, high-traffic code
 * serving every user, not just imported ones). See docs/legacy-data-import-plan.md §9.
 *
 * Fires on every User save in the app; a no-op for the overwhelming majority (no
 * password_hash transition, or no import_account_claims row at all — i.e. every
 * native SPIMS user).
 */
class UserObserver
{
    public function __construct(
        private readonly AuditLogWriter $audit,
    ) {}

    public function updated(User $user): void
    {
        if (! $user->wasChanged('password_hash')) {
            return;
        }

        // Only a genuine null -> set transition counts as "claimed". A later password
        // reset (already non-null -> non-null) must never re-touch the claim row.
        if ($user->getOriginal('password_hash') !== null || $user->password_hash === null) {
            return;
        }

        $claim = ImportAccountClaim::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [ImportAccountClaimStatus::Queued, ImportAccountClaimStatus::Sent])
            ->first();

        if ($claim === null) {
            return;
        }

        $claim->update([
            'status' => ImportAccountClaimStatus::Claimed,
            'claimed_at' => now(),
        ]);

        $this->audit->write($user, 'import.account_claim_claimed', ImportAccountClaim::class, $claim->id);
    }
}
