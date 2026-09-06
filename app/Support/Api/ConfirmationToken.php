<?php

namespace App\Support\Api;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Short-lived tokens for irreversible instructor API mutations:
 * gradebook.lock, offering.close, projects.announce, assessments.announce_results.
 */
class ConfirmationToken
{
    public function issue(User $actor, string $action, string $resourceId, array $consequences = []): array
    {
        $token = Str::lower(bin2hex(random_bytes(16))); // 32 hex chars
        Cache::put($this->key($actor, $action, $resourceId), $token, now()->addMinutes(10));

        return [
            'confirmation_token' => $token,
            'consequences' => array_values($consequences),
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ];
    }

    public function consume(User $actor, string $action, string $resourceId, ?string $token): void
    {
        if ($token === null || $token === '') {
            throw ValidationException::withMessages(['confirmation' => [__('api.confirmation_required')]]);
        }
        $cached = Cache::get($this->key($actor, $action, $resourceId));
        if (! is_string($cached) || ! hash_equals($cached, $token)) {
            throw ValidationException::withMessages(['confirmation' => [__('api.confirmation_invalid')]]);
        }
        Cache::forget($this->key($actor, $action, $resourceId));
    }

    private function key(User $actor, string $action, string $resourceId): string
    {
        return 'api:confirm:'.$actor->id.':'.$action.':'.$resourceId;
    }
}
