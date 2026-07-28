<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            GradingSchemeSeeder::class,
            ThemeSeeder::class,
            SettingsSeeder::class,
            SuperAdminSeeder::class,
            SampleDataSeeder::class,
        ]);
    }
}
