<?php

namespace App\Support\Api;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Short-lived tokens for irreversible instructor API mutations:
 * gradebook.lock, gradebook.reopen, offering.close, projects.announce, assessments.announce_results.
 *
 * A repeated GET reuses the unexpired token so a client refresh cannot invalidate
 * the value sitting in a confirmation dialog.
 */
class ConfirmationToken
{
    public function issue(User $actor, string $action, string $resourceId, array $consequences = []): array
    {
        $key = $this->key($actor, $action, $resourceId);
        $cached = Cache::get($key);
        $existing = $this->storedToken($cached);
        $expiresAt = $this->storedExpiry($cached);

        if ($existing !== null && $expiresAt !== null && $expiresAt->isFuture()) {
            return [
                'confirmation_token' => $existing,
                'consequences' => array_values($consequences),
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        }

        $token = Str::lower(bin2hex(random_bytes(16)));
        $expiresAt = now()->addMinutes(10);
        Cache::put($key, [
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'confirmation_token' => $token,
            'consequences' => array_values($consequences),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function consume(User $actor, string $action, string $resourceId, ?string $token): void
    {
        if ($token === null || $token === '') {
            throw ValidationException::withMessages(['confirmation' => [__('api.confirmation_required')]]);
        }
        $key = $this->key($actor, $action, $resourceId);
        $stored = $this->storedToken(Cache::get($key));
        if (! is_string($stored) || ! hash_equals($stored, $token)) {
            throw ValidationException::withMessages(['confirmation' => [__('api.confirmation_invalid')]]);
        }
        Cache::forget($key);
    }

    private function key(User $actor, string $action, string $resourceId): string
    {
        return 'api:confirm:'.$actor->id.':'.$action.':'.$resourceId;
    }

    private function storedToken(mixed $cached): ?string
    {
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        if (is_array($cached) && is_string($cached['token'] ?? null) && $cached['token'] !== '') {
            return $cached['token'];
        }

        return null;
    }

    private function storedExpiry(mixed $cached): ?Carbon
    {
        if (! is_array($cached) || ! is_string($cached['expires_at'] ?? null)) {
            return null;
        }

        return Carbon::parse($cached['expires_at']);
    }
}
