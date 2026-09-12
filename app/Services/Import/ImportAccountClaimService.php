<?php

namespace App\Services\Import;

use App\Enums\ImportAccountClaimStatus;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Mail\ImportAccountClaimMail;
use App\Models\ImportAccountClaim;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Support\AuditLogWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * L5 — sends and manages account-claim invitations for the ACTIVE-population cohort.
 * See docs/legacy-data-import-plan.md §9 and §11.11.
 *
 * Queueing a claim row happens inside ImportBatchService::commit() (never sends
 * mail); this service is the separate, explicit, permissioned action that actually
 * emails a reviewed cohort.
 */
class ImportAccountClaimService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * Sends (or re-sends after a bounce correction) an invitation to every QUEUED
     * claim in the given selection, in throttled chunks. Idempotent: a claim already
     * SENT, CLAIMED, EXPIRED or CANCELLED is skipped, not re-emailed — re-selecting
     * the same rows and pressing send again does nothing extra.
     *
     * @param  Collection<int, ImportAccountClaim>  $claims
     * @return array{sent: int, skipped: int}
     */
    public function send(User $actor, Collection $claims): array
    {
        return $this->audit->withAudit($actor, 'import.account_claim_send', function () use ($claims) {
            $sent = 0;
            $skipped = 0;
            $chunkSize = max(1, (int) config('import.claim_send_chunk_size', 25));
            $delayMs = max(0, (int) config('import.claim_send_chunk_delay_ms', 200));

            foreach ($claims->chunk($chunkSize) as $index => $chunk) {
                if ($index > 0 && $delayMs > 0) {
                    usleep($delayMs * 1000);
                }

                foreach ($chunk as $claim) {
                    if ($this->sendOne($claim)) {
                        $sent++;
                    } else {
                        $skipped++;
                    }
                }
            }

            return ['sent' => $sent, 'skipped' => $skipped];
        }, entityType: ImportAccountClaim::class);
    }

    private function sendOne(ImportAccountClaim $claim): bool
    {
        if ($claim->status !== ImportAccountClaimStatus::Queued) {
            return false;
        }

        $user = $claim->user;
        if ($user === null || $user->status !== UserStatus::Pending) {
            return false;
        }

        $code = $this->otp->issueSilently($user, OtpPurpose::EmailVerification);
        $ttlDays = max(1, (int) config('import.claim_link_ttl_days', 14));
        $claimUrl = URL::temporarySignedRoute(
            'import.claim.show',
            now()->addDays($ttlDays),
            ['user' => $user->id],
        );

        $locale = in_array($user->preferred_locale, ['ar', 'en', 'fr'], true) ? $user->preferred_locale : 'en';

        Mail::to($user->email)
            ->locale($locale)
            ->send(new ImportAccountClaimMail($user, $code, $claimUrl));

        $claim->update([
            'status' => ImportAccountClaimStatus::Sent,
            'invited_at' => $claim->invited_at ?? now(),
            'last_reminded_at' => $claim->invited_at !== null ? now() : $claim->last_reminded_at,
            'reminder_count' => $claim->invited_at !== null ? $claim->reminder_count + 1 : $claim->reminder_count,
        ]);

        return true;
    }

    /**
     * The documented fallback for bounce handling (§9): no bounce-webhook
     * infrastructure exists in this app, so an admin marks a claim BOUNCED by hand
     * from the activation screen worklist.
     */
    public function markBounced(User $actor, ImportAccountClaim $claim): ImportAccountClaim
    {
        return $this->audit->withAudit($actor, 'import.account_claim_bounced', function () use ($claim) {
            $claim->update(['status' => ImportAccountClaimStatus::Bounced]);

            return $claim->fresh();
        }, entityType: ImportAccountClaim::class);
    }

    /**
     * Corrects the address on a bounced claim and re-queues it for the next send —
     * the "inline corrected-email field" from §9 / §11.11.
     */
    public function correctEmailAndRequeue(User $actor, ImportAccountClaim $claim, string $newEmail): ImportAccountClaim
    {
        if ($claim->user === null) {
            throw new RuntimeException('This claim has no linked user.');
        }

        return $this->audit->withAudit($actor, 'import.account_claim_email_corrected', function () use ($claim, $newEmail) {
            $claim->user->update(['email' => strtolower($newEmail)]);
            $claim->update(['status' => ImportAccountClaimStatus::Queued]);

            return $claim->fresh();
        }, entityType: ImportAccountClaim::class);
    }
}
