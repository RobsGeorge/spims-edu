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

        // Always log OTP in log/array mail environments (dev + CI); never block.
        Log::info('SPIMS OTP issued', [
            'email' => $user->email,
            'purpose' => $purpose->value,
            'code' => $plain,
        ]);

        $this->mailer->send(
            (string) $user->email,
            'SPIMS OTP',
            'Your verification code is: '.$plain
        );

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
