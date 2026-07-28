<?php

namespace App\Services\Admin;

use App\Models\Theme;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;

class ThemeAdminService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function update(User $actor, Theme $theme, array $data): Theme
    {
        $this->authorize->authorize($actor, 'theme.manage');

        if (! empty($data['is_active'])) {
            Theme::query()->where('id', '!=', $theme->id)->update(['is_active' => false]);
        }

        $theme->update([
            'name' => $data['name'] ?? $theme->name,
            'site_name' => $data['site_name'] ?? $theme->site_name,
            'logo_light_url' => $data['logo_light_url'] ?? $theme->logo_light_url,
            'logo_dark_url' => $data['logo_dark_url'] ?? $theme->logo_dark_url,
            'favicon_url' => $data['favicon_url'] ?? $theme->favicon_url,
            'tokens' => $data['tokens'] ?? $theme->tokens,
            'is_active' => $data['is_active'] ?? $theme->is_active,
            'updated_by_id' => $actor->id,
        ]);

        $this->audit->write($actor, 'theme.update', 'Theme', $theme->id);

        return $theme->fresh();
    }
}
