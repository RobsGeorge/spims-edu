<?php

namespace App\Services\SuperAdmin;

use App\Models\Setting;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FeatureFlagService
{
    /** @var array<string, bool> */
    private array $memo = [];

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function enabled(string $key): bool
    {
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $definition = $this->definition($key);
        $default = (bool) ($definition['default'] ?? true);

        if (! Schema::hasTable('settings')) {
            return $this->memo[$key] = $default;
        }

        $row = Setting::query()->find($this->settingKey($key));
        if ($row === null || ! is_array($row->value) || ! array_key_exists('value', $row->value)) {
            return $this->memo[$key] = $default;
        }

        return $this->memo[$key] = (bool) $row->value['value'];
    }

    /**
     * @return list<array{
     *     key: string,
     *     enabled: bool,
     *     default: bool,
     *     group: string,
     *     overridden: bool
     * }>
     */
    public function catalog(): array
    {
        $items = [];

        foreach (array_keys(config('features', [])) as $key) {
            if (! $this->isVisible($key)) {
                continue;
            }

            $definition = $this->definition($key);
            $enabled = $this->enabled($key);
            $default = (bool) ($definition['default'] ?? true);
            $row = Schema::hasTable('settings')
                ? Setting::query()->find($this->settingKey($key))
                : null;

            $items[] = [
                'key' => $key,
                'enabled' => $enabled,
                'default' => $default,
                'group' => (string) ($definition['group'] ?? 'student'),
                'overridden' => $row !== null && is_array($row->value) && array_key_exists('value', $row->value),
            ];
        }

        return $items;
    }

    public function set(User $actor, string $key, bool $enabled): void
    {
        $this->authorize->authorize($actor, 'features.manage');

        if ($this->definition($key) === [] || ! $this->isVisible($key)) {
            throw ValidationException::withMessages([
                'key' => [__('features.unknown_key')],
            ]);
        }

        $settingKey = $this->settingKey($key);
        $before = ['enabled' => $this->enabled($key)];

        DB::transaction(function () use ($actor, $settingKey, $enabled, $before, $key): void {
            Setting::query()->updateOrCreate(
                ['key' => $settingKey],
                [
                    'value' => ['value' => $enabled],
                    'updated_by_id' => $actor->id,
                ]
            );

            $this->audit->write($actor, 'features.toggle', 'Setting', $key, $before, [
                'enabled' => $enabled,
            ]);
        });

        $this->memo[$key] = $enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(string $key): array
    {
        $row = config('features.'.$key);

        return is_array($row) ? $row : [];
    }

    public function settingKey(string $key): string
    {
        return str_starts_with($key, 'features.') ? $key : 'features.'.$key;
    }

    public function isVisible(string $key): bool
    {
        $definition = $this->definition($key);
        if ($definition === []) {
            return false;
        }

        $route = $definition['requires_route'] ?? null;
        if (is_string($route) && $route !== '' && ! Route::has($route)) {
            return false;
        }

        return true;
    }
}
