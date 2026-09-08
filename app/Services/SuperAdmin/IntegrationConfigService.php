<?php

namespace App\Services\SuperAdmin;

use App\Enums\PaymentMethod;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class IntegrationConfigService
{
    public const IDENTITY_TRANSACTIONAL = 'transactional';

    public const IDENTITY_NOTIFICATIONS = 'notifications';

    /** @var array<string, mixed>|null */
    private ?array $rows = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function safeDefinitions(): array
    {
        $rows = config('integrations.safe', []);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function secretDefinitions(): array
    {
        $rows = config('integrations.secrets', []);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Overlay allowlisted portal values onto Laravel config. Env remains the
     * fallback. Never writes .env.
     */
    public function applyRuntime(): void
    {
        if (! $this->ready()) {
            return;
        }

        $mailer = $this->safeString('integrations.mail.mailer');
        if ($mailer !== null) {
            config(['mail.default' => $mailer]);
        }

        $host = $this->safeString('integrations.mail.host');
        if ($host !== null) {
            config(['mail.mailers.smtp.host' => $host]);
        }

        $port = $this->safeInt('integrations.mail.port');
        if ($port !== null) {
            config(['mail.mailers.smtp.port' => $port]);
        }

        $encryption = $this->safeString('integrations.mail.encryption');
        if ($encryption !== null) {
            config(['mail.mailers.smtp.encryption' => $encryption === '' ? null : $encryption]);
        }

        $username = $this->safeString('integrations.mail.username');
        if ($username !== null) {
            config(['mail.mailers.smtp.username' => $username]);
        }

        $password = $this->secretValue('integrations.mail.password');
        if ($password !== null) {
            config(['mail.mailers.smtp.password' => $password]);
        }

        config([
            'mail.from.address' => $this->fromAddress(self::IDENTITY_TRANSACTIONAL),
            'mail.from.name' => $this->fromName(self::IDENTITY_TRANSACTIONAL),
            'services.paypal.enabled' => $this->gatewayEnabled(PaymentMethod::Paypal),
            'services.paypal.mode' => $this->paypalMode(),
            'services.paypal.client_id' => $this->paypalClientId(),
            'services.paymob.enabled' => $this->gatewayEnabled(PaymentMethod::Paymob),
            'services.cashier.enabled' => $this->gatewayEnabled(PaymentMethod::Cashier),
        ]);

        $paypalSecret = $this->secretValue('integrations.paypal.secret');
        if ($paypalSecret !== null) {
            config(['services.paypal.secret' => $paypalSecret]);
        }

        $paypalWebhook = $this->secretValue('integrations.paypal.webhook_id');
        if ($paypalWebhook !== null) {
            config(['services.paypal.webhook_id' => $paypalWebhook]);
        }

        $paymobKey = $this->secretValue('integrations.paymob.api_key');
        if ($paymobKey !== null) {
            config(['services.paymob.api_key' => $paymobKey]);
        }

        $paymobHmac = $this->secretValue('integrations.paymob.hmac');
        if ($paymobHmac !== null) {
            config(['services.paymob.hmac' => $paymobHmac]);
        }

        $integrationId = $this->safeString('integrations.paymob.integration_id');
        if ($integrationId !== null) {
            config(['services.paymob.integration_id' => $integrationId]);
        }

        $cashierSecret = $this->secretValue('integrations.cashier.secret');
        if ($cashierSecret !== null) {
            config(['services.cashier.secret' => $cashierSecret]);
        }
    }

    public function refresh(): void
    {
        $this->rows = null;
        $this->applyRuntime();
    }

    public function fromAddress(string $identity): string
    {
        $key = 'integrations.mail.'.$identity.'.from_address';
        $stored = $this->safeString($key);
        if ($stored !== null) {
            return $stored;
        }

        if ($identity === self::IDENTITY_NOTIFICATIONS) {
            return $this->fromAddress(self::IDENTITY_TRANSACTIONAL);
        }

        $env = config('mail.from.address');

        return is_string($env) && $env !== '' ? $env : 'noreply@spims-edu.com';
    }

    public function fromName(string $identity): string
    {
        $key = 'integrations.mail.'.$identity.'.from_name';
        $stored = $this->safeString($key);
        if ($stored !== null) {
            return $stored;
        }

        if ($identity === self::IDENTITY_NOTIFICATIONS) {
            return $this->fromName(self::IDENTITY_TRANSACTIONAL);
        }

        $school = $this->schoolFromName();
        if ($school !== null) {
            return $school;
        }

        $env = config('mail.from.name');

        return is_string($env) && $env !== '' ? $env : 'SPIMS';
    }

    public function gatewayEnabled(PaymentMethod $method): bool
    {
        $key = match ($method) {
            PaymentMethod::Paypal => 'integrations.paypal.enabled',
            PaymentMethod::Paymob => 'integrations.paymob.enabled',
            PaymentMethod::Cashier => 'integrations.cashier.enabled',
            default => null,
        };

        if ($key === null) {
            return true;
        }

        $stored = $this->safeBool($key);
        if ($stored !== null) {
            return $stored;
        }

        $default = $this->safeDefinitions()[$key]['default'] ?? true;

        return (bool) $default;
    }

    public function paypalMode(): string
    {
        $stored = $this->safeString('integrations.paypal.mode');
        if ($stored === 'live' || $stored === 'sandbox') {
            return $stored;
        }

        return app()->isProduction() ? 'live' : 'sandbox';
    }

    public function paypalApiBase(): string
    {
        return $this->paypalMode() === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function paypalClientId(): string
    {
        $stored = $this->safeString('integrations.paypal.client_id');
        if ($stored !== null) {
            return $stored;
        }

        $env = config('services.paypal.client_id');

        return is_string($env) ? $env : '';
    }

    public function hasStoredSecret(string $key): bool
    {
        return $this->secretValue($key) !== null;
    }

    public function secretConfigured(string $key): bool
    {
        if ($this->hasStoredSecret($key)) {
            return true;
        }

        $envKey = (string) ($this->secretDefinitions()[$key]['env'] ?? '');

        return $this->envFilled($envKey);
    }

    public function slotConfigured(string $id, string $envKey): bool
    {
        $secretKey = match ($id) {
            'mail_password' => 'integrations.mail.password',
            'paypal' => 'integrations.paypal.secret',
            'paymob' => 'integrations.paymob.api_key',
            'cashier' => 'integrations.cashier.secret',
            default => null,
        };

        if ($secretKey !== null && $this->hasStoredSecret($secretKey)) {
            return true;
        }

        if ($id === 'mail_username' && $this->safeString('integrations.mail.username') !== null) {
            return true;
        }

        return $this->envFilled($envKey);
    }

    public function displayedSafeValue(string $key): mixed
    {
        $definition = $this->safeDefinitions()[$key] ?? [];
        $stored = $this->rawValue($key);

        if ($stored !== null) {
            return $stored;
        }

        $type = (string) ($definition['type'] ?? 'string');
        if (array_key_exists('default', $definition) && ($definition['nullable'] ?? false) !== true) {
            return $definition['default'];
        }

        if ($type === 'bool') {
            return (bool) ($definition['default'] ?? true);
        }

        if ($type === 'select' && isset($definition['default'])) {
            return $definition['default'];
        }

        $envKey = (string) ($definition['env'] ?? '');
        if ($envKey === '') {
            return $type === 'bool' ? (bool) ($definition['default'] ?? false) : '';
        }

        $env = env($envKey);
        if ($env === null || $env === false) {
            return $type === 'bool' ? (bool) ($definition['default'] ?? false) : '';
        }

        return $env;
    }

    public function encryptSecret(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    public function ready(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }

    public function safeString(string $key): ?string
    {
        $value = $this->rawValue($key);
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function safeInt(string $key): ?int
    {
        $value = $this->rawValue($key);
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function safeBool(string $key): ?bool
    {
        $value = $this->rawValue($key);
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function secretValue(string $key): ?string
    {
        $row = $this->row($key);
        if (! is_array($row) || ! isset($row['encrypted']) || ! is_string($row['encrypted']) || $row['encrypted'] === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($row['encrypted']);
        } catch (DecryptException $e) {
            Log::warning('Integration secret could not be decrypted', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $plain = trim($plain);

        return $plain === '' ? null : $plain;
    }

    private function schoolFromName(): ?string
    {
        $row = $this->row('mail.from_name');
        if (! is_array($row)) {
            return null;
        }

        $value = $row['value'] ?? null;
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function envFilled(string $envKey): bool
    {
        if ($envKey === '') {
            return false;
        }

        $raw = env($envKey);

        return is_string($raw) ? trim($raw) !== '' : $raw !== null && $raw !== false;
    }

    private function rawValue(string $key): mixed
    {
        $row = $this->row($key);
        if (! is_array($row)) {
            return null;
        }

        if (array_key_exists('value', $row)) {
            return $row['value'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $key): ?array
    {
        $rows = $this->loaded();

        return $rows[$key] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loaded(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        if (! $this->ready()) {
            $this->rows = [];

            return $this->rows;
        }

        $this->rows = [];
        foreach (Setting::query()->get() as $setting) {
            if (is_array($setting->value)) {
                $this->rows[(string) $setting->key] = $setting->value;
            }
        }

        return $this->rows;
    }
}
