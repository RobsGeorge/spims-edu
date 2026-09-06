<?php

namespace Tests\Unit\Support;

use App\Support\ProductionSafeDefaults;
use App\Support\WebhookSecretGuard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionSafeDefaultsTest extends TestCase
{
    #[Test]
    public function mock_auto_complete_defaults_false_outside_local_and_testing(): void
    {
        $this->assertFalse(ProductionSafeDefaults::paymentsMockAutoComplete(null, 'production'));
        $this->assertFalse(ProductionSafeDefaults::paymentsMockAutoComplete(null, 'staging'));
        $this->assertFalse(ProductionSafeDefaults::paymentsMockAutoComplete(true, 'production'));
        $this->assertFalse(ProductionSafeDefaults::paymentsMockAutoComplete('true', 'staging'));
    }

    #[Test]
    public function mock_auto_complete_defaults_true_in_local_and_testing_when_unset(): void
    {
        $this->assertTrue(ProductionSafeDefaults::paymentsMockAutoComplete(null, 'local'));
        $this->assertTrue(ProductionSafeDefaults::paymentsMockAutoComplete(null, 'testing'));
        $this->assertTrue(ProductionSafeDefaults::paymentsMockAutoComplete('', 'local'));
        $this->assertFalse(ProductionSafeDefaults::paymentsMockAutoComplete(false, 'local'));
        $this->assertFalse(ProductionSafeDefaults::paymentsMockAutoComplete('false', 'testing'));
    }

    #[Test]
    public function webhook_test_defaults_are_detected(): void
    {
        $this->assertTrue(WebhookSecretGuard::isTestDefault('paypal-test'));
        $this->assertTrue(WebhookSecretGuard::isTestDefault('paymob-test'));
        $this->assertTrue(WebhookSecretGuard::isTestDefault('cashier-test'));
        $this->assertTrue(WebhookSecretGuard::isTestDefault('zoom-test'));
        $this->assertTrue(WebhookSecretGuard::isTestDefault(null));
        $this->assertTrue(WebhookSecretGuard::isTestDefault(''));
        $this->assertFalse(WebhookSecretGuard::isTestDefault('live-hmac-secret'));
    }

    #[Test]
    public function sanctum_expiration_is_finite(): void
    {
        $this->assertSame(60 * 24 * 30, (int) config('sanctum.expiration'));
    }
}
