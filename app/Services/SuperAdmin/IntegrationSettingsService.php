<?php

namespace App\Services\SuperAdmin;

use App\Enums\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Finance\GatewayRouter;
use App\Services\Mail\TransactionalMailer;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IntegrationSettingsService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly IntegrationConfigService $config,
        private readonly TransactionalMailer $mailer,
        private readonly GatewayRouter $gateways,
    ) {}

    public function authorize(User $actor): void
    {
        $this->authorize->authorize($actor, 'integrations.manage');
    }

    /**
     * @return array{
     *     groups: list<string>,
     *     safe: list<array<string, mixed>>,
     *     secrets: list<array<string, mixed>>,
     *     identities: list<string>,
     *     gateways: list<string>
     * }
     */
    public function catalog(): array
    {
        $safe = [];
        foreach ($this->config->safeDefinitions() as $key => $definition) {
            $type = (string) ($definition['type'] ?? 'string');
            $storedString = $this->config->safeString($key);
            $storedInt = $this->config->safeInt($key);
            $storedBool = $this->config->safeBool($key);
            $hasStored = $storedString !== null || $storedInt !== null || $storedBool !== null;
            $nullable = (bool) ($definition['nullable'] ?? false);

            $value = match ($type) {
                'bool' => $storedBool ?? (bool) ($definition['default'] ?? true),
                'int' => $hasStored ? $storedInt : '',
                default => $hasStored
                    ? ($storedString ?? $this->config->displayedSafeValue($key))
                    : (($nullable ? '' : ($definition['default'] ?? ''))),
            };

            $envKey = (string) ($definition['env'] ?? '');
            $hostHint = $envKey !== '' ? env($envKey) : null;

            $safe[] = [
                'key' => $key,
                'type' => $type,
                'group' => (string) ($definition['group'] ?? 'mail_transport'),
                'definition' => $definition,
                'value' => $value,
                'inherited' => ! $hasStored,
                'host_hint' => is_scalar($hostHint) ? (string) $hostHint : '',
            ];
        }

        $secrets = [];
        foreach ($this->config->secretDefinitions() as $key => $definition) {
            $secrets[] = [
                'key' => $key,
                'group' => (string) ($definition['group'] ?? 'mail_transport'),
                'env' => (string) ($definition['env'] ?? ''),
                'stored' => $this->config->hasStoredSecret($key),
                'configured' => $this->config->secretConfigured($key),
            ];
        }

        $identities = config('integrations.identities', [
            IntegrationConfigService::IDENTITY_TRANSACTIONAL,
            IntegrationConfigService::IDENTITY_NOTIFICATIONS,
        ]);
        $gateways = config('integrations.gateways', ['PAYPAL', 'PAYMOB', 'CASHIER']);

        return [
            'groups' => [
                'mail_transport',
                'mail_transactional',
                'mail_notifications',
                'paypal',
                'paymob',
                'cashier',
            ],
            'safe' => $safe,
            'secrets' => $secrets,
            'identities' => is_array($identities) ? array_values($identities) : [],
            'gateways' => is_array($gateways) ? array_values($gateways) : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $safe
     * @param  array<string, mixed>  $secrets
     * @param  array<string, mixed>  $clear
     */
    public function update(User $actor, array $safe, array $secrets, array $clear): void
    {
        $this->authorize($actor);

        $unknownSafe = array_values(array_diff(array_keys($safe), array_keys($this->config->safeDefinitions())));
        if ($unknownSafe !== []) {
            throw ValidationException::withMessages([
                'key' => [__('integrations.unknown_key', ['key' => $unknownSafe[0]])],
            ]);
        }

        $unknownSecrets = array_values(array_diff(array_keys($secrets), array_keys($this->config->secretDefinitions())));
        if ($unknownSecrets !== []) {
            throw ValidationException::withMessages([
                'key' => [__('integrations.unknown_key', ['key' => $unknownSecrets[0]])],
            ]);
        }

        $validatedSafe = $this->validateSafe($safe);
        $secretActions = $this->secretActions($secrets, $clear);
        $before = $this->auditSnapshot($validatedSafe, $secretActions);
        $after = $this->auditSnapshot($validatedSafe, $secretActions, pending: true);

        DB::transaction(function () use ($actor, $validatedSafe, $secretActions, $secrets, $before, $after): void {
            foreach ($validatedSafe as $key => $value) {
                if ($value === null) {
                    Setting::query()->where('key', $key)->delete();
                    continue;
                }

                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => ['value' => $value],
                        'updated_by_id' => $actor->id,
                    ]
                );
            }

            foreach ($secretActions as $key => $action) {
                if ($action === 'keep') {
                    continue;
                }

                if ($action === 'clear') {
                    Setting::query()->where('key', $key)->delete();
                    continue;
                }

                $plain = trim((string) ($secrets[$key] ?? ''));
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => ['encrypted' => $this->config->encryptSecret($plain)],
                        'updated_by_id' => $actor->id,
                    ]
                );
            }

            $this->audit->write($actor, 'integrations.update', 'Setting', null, $before, $after);
        });

        $this->config->refresh();
    }

    public function sendTestMail(User $actor, string $identity): void
    {
        $this->authorize($actor);

        $allowed = config('integrations.identities', [
            IntegrationConfigService::IDENTITY_TRANSACTIONAL,
            IntegrationConfigService::IDENTITY_NOTIFICATIONS,
        ]);
        if (! in_array($identity, is_array($allowed) ? $allowed : [], true)) {
            throw ValidationException::withMessages([
                'identity' => [__('integrations.unknown_identity')],
            ]);
        }

        $to = (string) $actor->email;
        $this->config->applyRuntime();
        $ok = $this->mailer->send(
            $to,
            __('integrations.test_mail_subject', ['identity' => $identity]),
            __('integrations.test_mail_body', ['identity' => $identity]),
            $identity
        );

        $this->audit->write($actor, 'integrations.test_mail', 'User', (string) $actor->id, null, [
            'identity' => $identity,
            'to' => $to,
            'ok' => $ok,
            'from_address' => $this->config->fromAddress($identity),
        ]);

        if (! $ok) {
            throw ValidationException::withMessages([
                'identity' => [__('integrations.test_mail_failed')],
            ]);
        }
    }

    public function sendTestPayment(User $actor, string $gateway, bool $confirmed): array
    {
        $this->authorize($actor);

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm' => [__('integrations.test_payment_confirm_required')],
            ]);
        }

        $method = PaymentMethod::tryFrom(strtoupper($gateway));
        $allowed = config('integrations.gateways', ['PAYPAL', 'PAYMOB', 'CASHIER']);
        if ($method === null || ! in_array($method->value, is_array($allowed) ? $allowed : [], true)) {
            throw ValidationException::withMessages([
                'gateway' => [__('integrations.unknown_gateway')],
            ]);
        }

        $this->config->applyRuntime();

        if (! $this->config->gatewayEnabled($method)) {
            throw ValidationException::withMessages([
                'gateway' => [__('integrations.gateway_disabled')],
            ]);
        }

        $result = $this->gateways->testCharge($method);

        $this->audit->write($actor, 'integrations.test_payment', 'Payment', null, null, [
            'gateway' => $method->value,
            'reference' => $result['reference'],
            'simulated' => $result['simulated'],
            'amount_minor' => $result['amount_minor'],
            'currency' => $result['currency'],
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateSafe(array $payload): array
    {
        $nested = [];
        $rules = [];

        foreach ($payload as $key => $value) {
            $definition = $this->config->safeDefinitions()[$key];
            data_set($nested, $key, $value);
            $rules[$key] = $this->rulesFor($definition);
        }

        $validatedNested = validator($nested, $rules)->validate();
        $validated = [];

        foreach ($payload as $key => $unused) {
            $value = data_get($validatedNested, $key);
            $definition = $this->config->safeDefinitions()[$key];
            $type = $definition['type'] ?? 'string';
            $nullable = (bool) ($definition['nullable'] ?? false);

            if ($type === 'bool') {
                $validated[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            if ($type === 'int') {
                if ($nullable && ($value === null || $value === '')) {
                    $validated[$key] = null;
                    continue;
                }
                $validated[$key] = (int) $value;
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($nullable && ($value === null || $value === '')) {
                $validated[$key] = null;
                continue;
            }

            $validated[$key] = $value;
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<mixed>
     */
    private function rulesFor(array $definition): array
    {
        $nullable = (bool) ($definition['nullable'] ?? false);
        $required = $nullable ? 'nullable' : 'required';

        return match ($definition['type'] ?? 'string') {
            'select' => [$required, 'string', Rule::in($definition['options'] ?? [])],
            'email' => [$required, 'email', 'max:255'],
            'int' => [
                $required,
                'integer',
                'min:'.(int) ($definition['min'] ?? 0),
                'max:'.(int) ($definition['max'] ?? 999999),
            ],
            'bool' => [$required],
            default => [$required, 'string', 'max:'.(int) ($definition['max'] ?? 255)],
        };
    }

    /**
     * @param  array<string, mixed>  $secrets
     * @param  array<string, mixed>  $clear
     * @return array<string, string>
     */
    private function secretActions(array $secrets, array $clear): array
    {
        $actions = [];

        foreach ($this->config->secretDefinitions() as $key => $unused) {
            $shouldClear = filter_var($clear[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
            $plain = trim((string) ($secrets[$key] ?? ''));

            if ($shouldClear) {
                $actions[$key] = 'clear';
                continue;
            }

            if ($plain !== '') {
                $actions[$key] = 'set';
                continue;
            }

            $actions[$key] = 'keep';
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $safe
     * @param  array<string, string>  $secretActions
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $safe, array $secretActions, bool $pending = false): array
    {
        $snapshot = [];

        foreach ($this->config->safeDefinitions() as $key => $unused) {
            if ($pending && array_key_exists($key, $safe)) {
                $snapshot[$key] = $safe[$key];
                continue;
            }
            $snapshot[$key] = $this->config->displayedSafeValue($key);
        }

        foreach ($secretActions as $key => $action) {
            if ($pending) {
                $snapshot[$key] = match ($action) {
                    'set' => 'changed',
                    'clear' => 'cleared',
                    default => $this->config->hasStoredSecret($key) ? 'stored' : 'env_or_missing',
                };
                continue;
            }

            $snapshot[$key] = $this->config->hasStoredSecret($key) ? 'stored' : 'env_or_missing';
        }

        return $snapshot;
    }
}
