<?php

namespace Tests\Unit\Finance;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Services\Finance\GatewayRouter;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GatewayRouterTest extends TestCase
{
    #[Test]
    public function charge_never_opens_http_in_testing_when_live_keys_are_present(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        config([
            'services.payments.mock_auto_complete' => false,
            'services.paypal.client_id' => 'cid',
            'services.paypal.secret' => 'csecret',
            'services.paymob.api_key' => 'pkey',
            'services.paymob.hmac' => 'live-hmac-secret',
            'services.cashier.secret' => 'sk_live_secret',
        ]);

        $router = app(GatewayRouter::class);
        $ref = $router->charge(PaymentMethod::Paypal, 1000, Currency::Usd, 'unit-1');

        $this->assertStringStartsWith('PAYPAL-', $ref);
        Http::assertNothingSent();
    }

    #[Test]
    public function verify_signature_uses_json_hmac_in_testing(): void
    {
        $payload = ['id' => 'evt-unit'];
        $secret = config('services.paypal.webhook_id');
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        $this->assertTrue(app(GatewayRouter::class)->verifySignature(
            PaymentMethod::Paypal,
            $signature,
            $payload
        ));

        $this->assertFalse(app(GatewayRouter::class)->verifySignature(
            PaymentMethod::Paypal,
            'deadbeef',
            $payload
        ));
    }
}
