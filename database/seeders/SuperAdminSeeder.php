<?php

namespace Database\Seeders;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'robeir.george@outlook.com');
        $password = env('SUPERADMIN_PASSWORD', 'Spims@Dev2026!');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'George',
                'last_name' => 'Robei',
                'password_hash' => Hash::make($password),
                'email_verified' => true,
                'preferred_locale' => 'en',
                'status' => UserStatus::Active,
            ]
        );

        UserRole::query()->updateOrCreate(
            ['user_id' => $user->id, 'role' => RoleType::SuperAdmin],
            ['role' => RoleType::SuperAdmin]
        );
    }
}
