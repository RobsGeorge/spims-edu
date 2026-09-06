<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * One-time session token for irreversible Blade mutations (the web counterpart
 * of the API confirmation_token). Issue on GET; consume on POST.
 */
class ConfirmationToken
{
    public function issue(string $purpose): string
    {
        $token = bin2hex(random_bytes(16));

        session()->put($this->key($purpose), [
            'value' => $token,
            'expires' => now()->addMinutes(30)->getTimestamp(),
        ]);

        return $token;
    }

    public function consume(string $purpose, ?string $provided): void
    {
        $stored = session()->pull($this->key($purpose));
        $value = is_array($stored) ? (string) ($stored['value'] ?? '') : '';
        $expires = is_array($stored) ? (int) ($stored['expires'] ?? 0) : 0;

        if (
            $provided === null
            || $provided === ''
            || $value === ''
            || $expires < now()->getTimestamp()
            || ! hash_equals($value, $provided)
        ) {
            throw ValidationException::withMessages([
                'confirmation_token' => [__('staff.confirm.invalid')],
            ]);
        }
    }

    private function key(string $purpose): string
    {
        return 'staff_confirm.'.$purpose;
    }
}
