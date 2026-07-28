<?php

namespace App\Console\Commands;

use App\Services\Rbac\RolePermissionService;
use Illuminate\Console\Command;

class SyncRolePermissionsCommand extends Command
{
    protected $signature = 'permissions:sync {--force : Replace all role_permissions from config}';

    protected $description = 'Sync config/permissions.php defaults into role_permissions.';

    public function handle(RolePermissionService $rbac): int
    {
        $count = $rbac->syncFromConfig((bool) $this->option('force'));
        $this->info("Synced role permissions ({$count} rows written).");

        return self::SUCCESS;
    }
}
