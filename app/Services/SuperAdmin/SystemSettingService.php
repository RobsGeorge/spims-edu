<?php

namespace App\Services\SuperAdmin;

use App\Models\Setting;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SystemSettingService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        $rows = config('system_settings.editable', []);

        return is_array($rows) ? $rows : [];
    }

    public function value(string $key): mixed
    {
        $definition = $this->definitions()[$key] ?? null;
        $fallback = $definition['default'] ?? null;

        if (! Schema::hasTable('settings')) {
            return $fallback;
        }

        $row = Setting::query()->find($key);
        if ($row === null || ! is_array($row->value)) {
            return $fallback;
        }

        if (array_key_exists('value', $row->value)) {
            return $row->value['value'];
        }

        return $row->value;
    }

    /**
     * @return list<array{key: string, value: mixed, type: string, definition: array<string, mixed>}>
     */
    public function catalog(): array
    {
        $items = [];

        foreach ($this->definitions() as $key => $definition) {
            $items[] = [
                'key' => $key,
                'value' => $this->value($key),
                'type' => (string) ($definition['type'] ?? 'string'),
                'definition' => $definition,
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $actor, array $payload): void
    {
        $this->authorize->authorize($actor, 'system_settings.manage');

        $unknown = array_values(array_diff(array_keys($payload), array_keys($this->definitions())));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'key' => [__('system_settings.unknown_key', ['key' => $unknown[0]])],
            ]);
        }

        $validated = $this->validate($payload);
        $before = [];
        $after = [];

        foreach ($validated as $key => $value) {
            $before[$key] = $this->value($key);
            $after[$key] = $value;
        }

        DB::transaction(function () use ($actor, $validated, $before, $after): void {
            foreach ($validated as $key => $value) {
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => ['value' => $value],
                        'updated_by_id' => $actor->id,
                    ]
                );
            }

            $this->audit->write($actor, 'system_settings.update', 'Setting', null, $before, $after);
        });
    }

    /**
     * @return list<array{id: string, configured: bool}>
     */
    public function integrations(): array
    {
        $items = [];
        $map = config('system_settings.integrations', []);

        foreach ($map as $id => $row) {
            $envKey = (string) ($row['env'] ?? '');
            $raw = $envKey !== '' ? env($envKey) : null;
            $items[] = [
                'id' => (string) $id,
                'configured' => is_string($raw) ? trim($raw) !== '' : $raw !== null && $raw !== false,
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validate(array $payload): array
    {
        $nested = [];
        $rules = [];

        foreach ($payload as $key => $value) {
            $definition = $this->definitions()[$key];
            data_set($nested, $key, $value);
            $rules[$key] = $this->rulesFor($definition);
        }

        $validatedNested = validator($nested, $rules)->validate();

        $validated = [];
        foreach ($payload as $key => $unused) {
            $value = data_get($validatedNested, $key);
            $type = $this->definitions()[$key]['type'] ?? 'string';
            if ($type === 'int_list') {
                $value = $this->normalizeIntList($value);
            }
            if ($type === 'int') {
                $value = (int) $value;
            }
            if ($type === 'string') {
                $value = trim((string) $value);
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
        return match ($definition['type'] ?? 'string') {
            'locale' => ['required', 'string', Rule::in($definition['options'] ?? ['ar', 'en', 'fr'])],
            'timezone' => ['required', 'timezone'],
            'int' => [
                'required',
                'integer',
                'min:'.(int) ($definition['min'] ?? 0),
                'max:'.(int) ($definition['max'] ?? 999999),
            ],
            'int_list' => ['required', 'string'],
            default => ['required', 'string', 'max:'.(int) ($definition['max'] ?? 255)],
        };
    }

    /**
     * @return list<int>
     */
    private function normalizeIntList(mixed $value): array
    {
        $parts = is_array($value)
            ? $value
            : (preg_split('/[,\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $ints = array_values(array_map(static fn ($part) => (int) $part, $parts));

        if ($ints === []) {
            throw ValidationException::withMessages([
                'late_penalty.escalating' => [__('system_settings.int_list_invalid')],
            ]);
        }

        return $ints;
    }
}
