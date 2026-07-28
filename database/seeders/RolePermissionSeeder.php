<?php

namespace Database\Seeders;

use App\Services\Rbac\RolePermissionService;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(RolePermissionService::class)->syncFromConfig(force: false);
    }
}
