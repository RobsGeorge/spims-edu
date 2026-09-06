<?php

namespace App\Support\Api;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Two-step confirmation for irreversible API mutations (gradebook.lock,
 * assessments.announce_results). A GET issues a short-lived token; the
 * mutation must echo it in `confirmation`. Missing, invalid, and replayed
 * tokens fail on that field.
 */
class ConfirmationToken
{
    public function issue(User $actor, string $action, string $resourceId, array $consequences = []): array
    {
        $token = bin2hex(random_bytes(16));
        $expiresAt = now()->utc()->addMinutes(10);

        Cache::put($this->key($actor, $action, $resourceId), $token, $expiresAt);

        return [
            'confirmation_token' => $token,
            'consequences' => $consequences,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function consume(User $actor, string $action, string $resourceId, ?string $token): void
    {
        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'confirmation' => [__('api.confirmation_required')],
            ]);
        }

        $key = $this->key($actor, $action, $resourceId);
        $cached = Cache::get($key);

        if (! is_string($cached) || ! hash_equals($cached, $token)) {
            throw ValidationException::withMessages([
                'confirmation' => [__('api.confirmation_invalid')],
            ]);
        }

        Cache::forget($key);
    }

    private function key(User $actor, string $action, string $resourceId): string
    {
        return 'api:confirm:'.$actor->id.':'.$action.':'.$resourceId;
    }
}
