<?php

namespace App\Support\Api;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ConfirmationToken
{
    public const TTL_MINUTES = 10;

    public function issue(User $user, string $action, string $resourceId): string
    {
        $token = bin2hex(random_bytes(16));
        Cache::put($this->key($user, $action, $resourceId), $token, now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    public function consume(User $user, string $action, string $resourceId, mixed $presented): void
    {
        $key = $this->key($user, $action, $resourceId);
        $stored = Cache::get($key);

        if (! is_string($presented) || $presented === '' || ! is_string($stored) || ! hash_equals($stored, $presented)) {
            throw ValidationException::withMessages([
                'confirmation' => [__('api.confirmation_invalid')],
            ]);
        }

        Cache::forget($key);
    }

    private function key(User $user, string $action, string $resourceId): string
    {
        return 'api:confirm:'.$user->id.':'.$action.':'.$resourceId;
    }
}
