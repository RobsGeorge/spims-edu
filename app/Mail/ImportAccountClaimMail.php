<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "Your school records have moved to SPIMS, claim your account" — sent to the
 * ACTIVE-population cohort by ImportAccountClaimService::send(). The link inside
 * always resolves through the existing, unmodified auth.verify -> auth.password.create
 * flow (docs/legacy-data-import-plan.md §9); this mailable only supplies the framing,
 * the OTP code and the link, in the recipient's own preferred_locale.
 *
 * Sent synchronously via Mail::to()->send(), consistent with the rest of the app
 * (TransactionalMailer has no queue either) — no ShouldQueue.
 */
class ImportAccountClaimMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly string $code,
        public readonly string $claimUrl,
    ) {}

    public function build(): self
    {
        return $this->subject(__('import.claim_mail_subject'))
            ->view('mail.import.account-claim')
            ->with([
                'recipientName' => $this->recipient->displayName(),
                'code' => $this->code,
                'claimUrl' => $this->claimUrl,
                'rtl' => $this->locale === 'ar',
            ]);
    }
}
