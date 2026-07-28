<?php

namespace App\Services\Finance;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mock gateway router — PayPal for USD, Paymob/Cashier for EGP.
 * Real SDK calls replace charge()/verifySignature() later without changing callers.
 */
class GatewayRouter
{
    public function methodFor(Currency $currency, ?string $preferred = null): PaymentMethod
    {
        if ($preferred !== null) {
            $method = PaymentMethod::tryFrom(strtoupper($preferred));
            if ($method === null) {
                throw ValidationException::withMessages(['gateway' => [__('finance.unknown_gateway')]]);
            }

            return $method;
        }

        return $currency === Currency::Egp ? PaymentMethod::Paymob : PaymentMethod::Paypal;
    }

    public function charge(PaymentMethod $method, int $amountMinor, Currency $currency, string $paymentId): string
    {
        // Simulated successful charge
        return strtoupper($method->value).'-'.Str::ulid();
    }

    public function verifySignature(PaymentMethod $method, string $signature, array $payload): bool
    {
        $secret = match ($method) {
            PaymentMethod::Paypal => config('services.paypal.webhook_id', 'paypal-test'),
            PaymentMethod::Paymob => config('services.paymob.hmac', 'paymob-test'),
            PaymentMethod::Cashier => config('services.cashier.secret', 'cashier-test'),
            default => 'test',
        };

        $expected = hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($expected, $signature);
    }
}
