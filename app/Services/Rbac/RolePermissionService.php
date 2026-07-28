<?php

namespace App\Services\Rbac;

use App\Enums\RoleType;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * Sync defaults from config/permissions.php into role_permissions (idempotent fill).
     * Does not overwrite existing rows unless $force is true.
     */
    public function syncFromConfig(bool $force = false): int
    {
        $written = 0;
        $matrix = config('permissions', []);

        DB::transaction(function () use ($matrix, $force, &$written): void {
            if ($force) {
                RolePermission::query()->delete();
            }

            foreach ($matrix as $permissionKey => $roleLevels) {
                if (! is_array($roleLevels)) {
                    continue;
                }

                foreach ($roleLevels as $role => $level) {
                    if ($role === RoleType::SuperAdmin->value) {
                        continue;
                    }

                    if ($force) {
                        RolePermission::query()->create([
                            'role' => $role,
                            'permission_key' => $permissionKey,
                            'level' => (string) $level,
                        ]);
                        $written++;
                        continue;
                    }

                    $created = RolePermission::query()->firstOrCreate(
                        [
                            'role' => $role,
                            'permission_key' => $permissionKey,
                        ],
                        ['level' => (string) $level]
                    );

                    if ($created->wasRecentlyCreated) {
                        $written++;
                    }
                }
            }
        });

        return $written;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function matrix(): array
    {
        $out = [];
        foreach (RolePermission::query()->orderBy('permission_key')->get() as $row) {
            $out[$row->permission_key][$row->role->value] = $row->level;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        $fromDb = RolePermission::query()->distinct()->orderBy('permission_key')->pluck('permission_key')->all();
        if ($fromDb !== []) {
            return $fromDb;
        }

        return array_keys(config('permissions', []));
    }

    /**
     * @return array<string, list<string>>
     */
    public function groupedPermissionKeys(): array
    {
        $groups = [];
        foreach ($this->permissionKeys() as $key) {
            $group = str_contains($key, '.') ? explode('.', $key, 2)[0] : 'general';
            $groups[$group][] = $key;
        }
        ksort($groups);

        return $groups;
    }

    /**
     * Editable static roles (SUPER_ADMIN always bypasses and is not matrix-edited).
     *
     * @return list<RoleType>
     */
    public function editableRoles(): array
    {
        return array_values(array_filter(
            RoleType::cases(),
            fn (RoleType $role): bool => $role !== RoleType::SuperAdmin
        ));
    }

    /**
     * Replace grants for one role from checkbox list of permission keys.
     * Levels default to F when newly granted; keep existing level if already present in config defaults.
     *
     * @param  list<string>  $permissionKeys
     */
    public function updateRoleMatrix(User $actor, RoleType $role, array $permissionKeys): void
    {
        $this->authorize->authorize($actor, 'roles.manage_matrix');

        if ($role === RoleType::SuperAdmin) {
            abort(403);
        }

        $permissionKeys = array_values(array_unique(array_filter($permissionKeys)));
        $defaults = config('permissions', []);

        $this->audit->write($actor, 'rbac.role_matrix.update', 'RoleType', $role->value, null, [
            'permissions' => $permissionKeys,
        ]);

        DB::transaction(function () use ($role, $permissionKeys, $defaults): void {
            RolePermission::query()->where('role', $role->value)->delete();

            foreach ($permissionKeys as $key) {
                if (! array_key_exists($key, $defaults)) {
                    continue;
                }

                $level = $defaults[$key][$role->value] ?? 'F';

                RolePermission::query()->create([
                    'role' => $role->value,
                    'permission_key' => $key,
                    'level' => (string) $level,
                ]);
            }
        });

        $this->authorize->forgetMatrixCache();
    }
}
