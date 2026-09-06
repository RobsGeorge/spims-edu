<?php

namespace App\Support;

final class ProductionSafeDefaults
{
    /**
     * Mock gateway auto-complete is allowed only in local/testing.
     * Staging/production treat a missing PAYMENTS_MOCK_AUTO_COMPLETE as false.
     * An explicit true in staging/production is ignored — fail closed.
     */
    public static function paymentsMockAutoComplete(mixed $explicit, string $appEnv): bool
    {
        if (! in_array($appEnv, ['local', 'testing'], true)) {
            return false;
        }

        if ($explicit === null || $explicit === '') {
            return true;
        }

        return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
    }
}
