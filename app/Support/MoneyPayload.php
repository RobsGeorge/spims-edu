<?php

namespace App\Support;

use App\Enums\Currency;

final class MoneyPayload
{
    /**
     * @return array{minor_units: int, currency: string, formatted: string}
     */
    public static function fromMinor(int $minorUnits, Currency $currency): array
    {
        $money = Money::fromMinor($minorUnits, $currency);

        return [
            'minor_units' => $money->minorUnits,
            'currency' => $currency->value,
            'formatted' => $money->format(),
        ];
    }
}
