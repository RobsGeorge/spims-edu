<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Models\OtpToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpService
{
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

        Log::info('SPIMS OTP issued (mail skipped)', [
            'email' => $user->email,
            'purpose' => $purpose->value,
            'code' => $plain,
        ]);

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
