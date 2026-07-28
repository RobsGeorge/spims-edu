<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'ar', 'name' => 'العربية', 'is_rtl' => true, 'enabled' => true],
            ['code' => 'en', 'name' => 'English', 'is_rtl' => false, 'enabled' => true],
            ['code' => 'fr', 'name' => 'Français', 'is_rtl' => false, 'enabled' => true],
        ];

        foreach ($languages as $language) {
            Language::query()->updateOrCreate(
                ['code' => $language['code']],
                $language
            );
        }
    }
}
