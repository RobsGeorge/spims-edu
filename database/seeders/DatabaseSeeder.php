<?php

namespace Database\Seeders;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\GradingScheme;
use App\Models\Language;
use App\Models\Theme;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
        ]);
    }
}
