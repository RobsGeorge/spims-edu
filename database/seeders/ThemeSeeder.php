<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        Theme::query()->where('is_active', true)->update(['is_active' => false]);

        Theme::query()->updateOrCreate(
            ['name' => 'Liturgical'],
            [
                'is_active' => true,
                'site_name' => 'SPIMS',
                'tokens' => [
                    'light' => [
                        'bg1' => '#faf6ee',
                        'bg2' => '#f3ead8',
                        'bg3' => '#ebe0c8',
                        'surface' => 'rgba(255, 252, 245, 0.92)',
                        'surfaceBorder' => 'rgba(180, 140, 50, 0.2)',
                        'title' => '#5c4a1f',
                        'titleAccent' => '#8b6914',
                        'text' => '#3d3428',
                        'textMuted' => '#6b5d4a',
                        'link' => '#8b6914',
                        'primary' => '#b8860b',
                        'primaryHover' => '#9a7209',
                        'primaryText' => '#1a1408',
                        'navBg' => 'rgba(250, 246, 238, 0.95)',
                        'navText' => '#4a4035',
                        'navActive' => '#8b6914',
                    ],
                    'dark' => [
                        'bg1' => '#070f1f',
                        'bg2' => '#0f2744',
                        'bg3' => '#1a3a5c',
                        'surface' => 'rgba(15, 39, 68, 0.72)',
                        'surfaceBorder' => 'rgba(212, 175, 55, 0.18)',
                        'title' => '#d4af37',
                        'titleAccent' => '#f0d875',
                        'text' => '#f8fafc',
                        'textMuted' => '#cbd5e1',
                        'link' => '#f0d875',
                        'primary' => '#d4af37',
                        'primaryHover' => '#c9a227',
                        'primaryText' => '#0a1628',
                        'navBg' => 'rgba(7, 15, 31, 0.92)',
                        'navText' => '#e2e8f0',
                        'navActive' => '#d4af37',
                    ],
                ],
            ]
        );
    }
}
