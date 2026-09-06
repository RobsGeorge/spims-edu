<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class WebhookSecretGuard
{
    /**
     * Refuse HMAC verification when a *-test placeholder is still configured
     * in production. Local and testing keep those defaults for fixtures.
     */
    public static function assertSafeToVerify(?string $secret, string $service): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (! self::isTestDefault($secret)) {
            return;
        }

        Log::error('Refusing webhook verification: configured secret is still a *-test default', [
            'service' => $service,
        ]);

        throw new HttpException(503, __('api.webhook_secret_unconfigured'));
    }

    public static function isTestDefault(?string $secret): bool
    {
        if ($secret === null || $secret === '') {
            return true;
        }

        return str_ends_with($secret, '-test');
    }
}
