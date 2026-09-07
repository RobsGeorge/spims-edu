<?php

namespace App\Services\Admin;

use App\Models\Theme;
use App\Models\User;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use App\Support\ThemeTokens;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ThemeAdminService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly ObjectStorageService $storage,
    ) {}

    /**
     * @return list<Theme>
     */
    public function catalog(): array
    {
        return Theme::query()->orderByDesc('is_active')->orderBy('name')->get()->all();
    }

    public function createFromDefaults(User $actor, string $name): Theme
    {
        $this->authorize->authorize($actor, 'theme.manage');

        return $this->audit->withAudit($actor, 'theme.create', function () use ($actor, $name) {
            return Theme::query()->create([
                'name' => $name,
                'site_name' => 'SPIMS',
                'is_active' => false,
                'logo_light_url' => null,
                'logo_dark_url' => null,
                'favicon_url' => null,
                'tokens' => ThemeTokens::defaults(),
                'updated_by_id' => $actor->id,
            ]);
        }, 'Theme');
    }

    public function duplicate(User $actor, Theme $theme): Theme
    {
        $this->authorize->authorize($actor, 'theme.manage');

        return $this->audit->withAudit($actor, 'theme.duplicate', function () use ($actor, $theme) {
            return Theme::query()->create([
                'name' => __('theme_studio.copy_of', ['name' => $theme->name]),
                'site_name' => $theme->site_name,
                'is_active' => false,
                'logo_light_url' => $theme->logo_light_url,
                'logo_dark_url' => $theme->logo_dark_url,
                'favicon_url' => $theme->favicon_url,
                'tokens' => ThemeTokens::resolve($theme->tokens),
                'updated_by_id' => $actor->id,
            ]);
        }, 'Theme');
    }

    public function activate(User $actor, Theme $theme): Theme
    {
        $this->authorize->authorize($actor, 'theme.manage');

        $before = [
            'active_id' => Theme::query()->where('is_active', true)->value('id'),
        ];

        DB::transaction(function () use ($actor, $theme, $before): void {
            Theme::query()->where('id', '!=', $theme->id)->update(['is_active' => false]);
            $theme->update([
                'is_active' => true,
                'updated_by_id' => $actor->id,
            ]);
            $this->audit->write($actor, 'theme.activate', 'Theme', $theme->id, $before, [
                'active_id' => $theme->id,
            ]);
        });

        return $theme->fresh();
    }

    public function resetTokens(User $actor, Theme $theme): Theme
    {
        $this->authorize->authorize($actor, 'theme.manage');

        $before = ['tokens' => $theme->tokens];

        DB::transaction(function () use ($actor, $theme, $before): void {
            $theme->update([
                'tokens' => ThemeTokens::defaults(),
                'updated_by_id' => $actor->id,
            ]);
            $this->audit->write($actor, 'theme.reset', 'Theme', $theme->id, $before, [
                'tokens' => ThemeTokens::defaults(),
            ]);
        });

        return $theme->fresh();
    }

    public function update(User $actor, Theme $theme, array $data): Theme
    {
        $this->authorize->authorize($actor, 'theme.manage');

        if (array_key_exists('tokens', $data)) {
            $data['tokens'] = $this->sanitizeTokens($data['tokens'] ?? null, $theme->tokens);
        }

        if (! empty($data['is_active'])) {
            Theme::query()->where('id', '!=', $theme->id)->update(['is_active' => false]);
        }

        $theme->update([
            'name' => $data['name'] ?? $theme->name,
            'site_name' => $data['site_name'] ?? $theme->site_name,
            'logo_light_url' => array_key_exists('logo_light_url', $data)
                ? ($data['logo_light_url'] ?: null)
                : $theme->logo_light_url,
            'logo_dark_url' => array_key_exists('logo_dark_url', $data)
                ? ($data['logo_dark_url'] ?: null)
                : $theme->logo_dark_url,
            'favicon_url' => array_key_exists('favicon_url', $data)
                ? ($data['favicon_url'] ?: null)
                : $theme->favicon_url,
            'tokens' => $data['tokens'] ?? $theme->tokens,
            'is_active' => $data['is_active'] ?? $theme->is_active,
            'updated_by_id' => $actor->id,
        ]);

        $this->audit->write($actor, 'theme.update', 'Theme', $theme->id);

        return $theme->fresh();
    }

    /**
     * @param  'logo_light_url'|'logo_dark_url'|'favicon_url'  $field
     */
    public function storeAsset(User $actor, Theme $theme, string $field, UploadedFile $file): Theme
    {
        $this->authorize->authorize($actor, 'theme.manage');

        $allowed = ['logo_light_url', 'logo_dark_url', 'favicon_url'];
        if (! in_array($field, $allowed, true)) {
            throw ValidationException::withMessages([
                'field' => [__('theme_studio.unknown_asset')],
            ]);
        }

        $path = $this->storage->signedUploadPath(
            'logos',
            (string) $theme->id,
            $file->getClientOriginalExtension() ?: $file->extension()
        );
        $this->storage->store($path, $file->getContent());

        $before = [$field => $theme->{$field}];

        DB::transaction(function () use ($actor, $theme, $field, $path, $before): void {
            $theme->update([
                $field => $path,
                'updated_by_id' => $actor->id,
            ]);
            $this->audit->write($actor, 'theme.asset_upload', 'Theme', $theme->id, $before, [
                $field => $path,
            ]);
        });

        return $theme->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $incoming
     * @param  array<string, mixed>|null  $current
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function sanitizeTokens(?array $incoming, ?array $current): array
    {
        $allowed = ThemeTokens::keys();
        foreach (['light', 'dark'] as $mode) {
            $row = $incoming[$mode] ?? [];
            if (! is_array($row)) {
                continue;
            }
            $unknown = array_values(array_diff(array_keys($row), $allowed));
            if ($unknown !== []) {
                throw ValidationException::withMessages([
                    'tokens' => [__('theme_studio.unknown_token', ['key' => $unknown[0]])],
                ]);
            }
        }

        return ThemeTokens::resolve(array_replace_recursive($current ?? [], $incoming ?? []));
    }
}
