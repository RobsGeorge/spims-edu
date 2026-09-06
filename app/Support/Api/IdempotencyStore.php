<?php

namespace App\Support\Api;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Replay-safe cache for flaky-client mutations (assignment submit, attempt submit).
 * Same actor + same key + same scope returns the original payload and does not
 * invoke the callback again.
 */
class IdempotencyStore
{
    /**
     * @param  Closure(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public function remember(User $actor, string $scope, ?string $key, Closure $callback): array
    {
        if ($key === null || $key === '') {
            return $callback();
        }

        $cacheKey = 'api:idempotency:'.$actor->id.':'.$scope.':'.hash('sha256', $key);

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $payload = $callback();
        Cache::put($cacheKey, $payload, now()->addDay());

        return $payload;
    }
}
