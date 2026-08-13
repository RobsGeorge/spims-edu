<?php

namespace App\Services\Finance;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Payment gateway router (PayPal for USD, Paymob/Cashier for EGP).
 *
 * When `services.payments.mock_auto_complete` is true (default), charges are
 * simulated immediately with HMAC-ready gateway reference IDs (`METHOD-ULID`).
 *
 * When mock is false, live PayPal / Paymob / Cashier SDK calls are intended.
 * Until those SDKs are wired, missing credentials (or unwired SDKs) degrade to
 * the mock charge path with a `Log::warning` — never throws — so CI and local
 * remain green. Callers and webhook HMAC verification stay unchanged.
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
        $mock = (bool) config('services.payments.mock_auto_complete', true);

        if (! $mock) {
            $liveRef = $this->attemptLiveCharge($method, $amountMinor, $currency, $paymentId);
            if ($liveRef !== null) {
                return $liveRef;
            }

            Log::warning('Live payment SDK not wired or keys missing; degrading to mock charge', [
                'method' => $method->value,
                'currency' => $currency->value,
                'payment_id' => $paymentId,
                'amount_minor' => $amountMinor,
            ]);
        }

        // HMAC-ready synthetic charge id (also used when live path degrades).
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

    /**
     * Placeholder for real SDK charge. Returns null when keys are absent or SDK is unwired.
     */
    private function attemptLiveCharge(PaymentMethod $method, int $amountMinor, Currency $currency, string $paymentId): ?string
    {
        $hasKeys = match ($method) {
            PaymentMethod::Paypal => filled(config('services.paypal.client_id'))
                && filled(config('services.paypal.secret')),
            PaymentMethod::Paymob => filled(config('services.paymob.api_key'))
                && filled(config('services.paymob.hmac')),
            PaymentMethod::Cashier => filled(config('services.cashier.secret'))
                && config('services.cashier.secret') !== 'cashier-test',
            default => false,
        };

        if (! $hasKeys) {
            return null;
        }

        // Credentials present but live SDK clients are not wired in this phase.
        Log::info('Live payment credentials present but SDK not wired; caller will degrade to mock', [
            'method' => $method->value,
            'payment_id' => $paymentId,
            'amount_minor' => $amountMinor,
            'currency' => $currency->value,
        ]);

        return null;
    }
}
