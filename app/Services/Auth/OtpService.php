<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Models\OtpToken;
use App\Models\User;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function __construct(
        private readonly TransactionalMailer $mailer,
    ) {}

    public function issue(User $user, OtpPurpose $purpose): string
    {
        $plain = $this->issueSilently($user, $purpose);

        $this->mailer->send(
            (string) $user->email,
            'SPIMS OTP',
            'Your verification code is: '.$plain
        );

        return $plain;
    }

    /**
     * Same token creation as issue() — same OtpToken table, same purpose, same
     * hash/expiry — but without sending the generic OTP mail. For callers that send
     * their own contextual message containing this code (e.g. the legacy-import
     * account-claim invite, docs/legacy-data-import-plan.md §9), so a claimant never
     * receives two emails for one invitation.
     */
    public function issueSilently(User $user, OtpPurpose $purpose): string
    {
        OtpToken::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $plain = (string) random_int(100000, 999999);

        OtpToken::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($plain),
            'expires_at' => now()->addMinutes(15),
        ]);

        // Never write the digits to laravel.log. Local verify uses the mail log
        // (MAIL_MAILER=log) and the session UI, not application error/info logs.
        if (in_array((string) config('mail.default'), ['log', 'array'], true)) {
            Log::notice('SPIMS OTP issued', [
                'user_id' => $user->id,
                'purpose' => $purpose->value,
            ]);
        }

        return $plain;
    }

    public function verify(User $user, OtpPurpose $purpose, string $code): bool
    {
        $token = OtpToken::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if ($token === null || ! Hash::check($code, $token->code_hash)) {
            return false;
        }

        $token->update(['consumed_at' => now()]);

        return true;
    }
}
