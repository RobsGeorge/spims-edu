<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Support\ThemeTokens;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        Theme::query()->where('is_active', true)->update(['is_active' => false]);

        // Retire parchment-era preset if present.
        Theme::query()
            ->where('name', 'Liturgical')
            ->update(['is_active' => false]);

        Theme::query()->updateOrCreate(
            ['name' => 'Sacred Academic'],
            [
                'is_active' => true,
                'site_name' => 'SPIMS',
                'logo_light_url' => null,
                'logo_dark_url' => null,
                'favicon_url' => null,
                'tokens' => ThemeTokens::defaults(),
            ]
        );
    }
}
