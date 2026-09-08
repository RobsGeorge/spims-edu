<?php

namespace App\Services\Rbac;

use App\Enums\RoleType;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RolePermissionService
{
    /**
     * Grant tokens Super Admin may store. Empty/off is deny (no row), never a default F.
     *
     * @var list<string>
     */
    public const GRANT_LEVELS = ['R', 'O', 'F', 'lock', 'reopen', 'issue'];

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
        $fromConfig = array_keys(config('permissions', []));
        $keys = array_values(array_unique(array_merge($fromConfig, $fromDb)));
        sort($keys);

        return $keys;
    }

    public function groupLabel(string $group): string
    {
        $key = 'roles_hub.group_'.$group;
        $label = __($key);

        return $label === $key ? $group : $label;
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
     * Replace grants for one role from an explicit permission_key => level map.
     * Empty/off omits the row (deny). Unknown keys and unknown levels are rejected.
     * A new grant is never defaulted to F.
     *
     * @param  array<string, string|null>  $permissionLevels
     */
    public function updateRoleMatrix(User $actor, RoleType $role, array $permissionLevels): void
    {
        $this->authorize->authorize($actor, 'roles.manage_matrix');

        if ($role === RoleType::SuperAdmin) {
            abort(403);
        }

        $grants = $this->validatedGrants($permissionLevels);

        DB::transaction(function () use ($actor, $role, $grants): void {
            $before = RolePermission::query()
                ->where('role', $role->value)
                ->pluck('level', 'permission_key')
                ->all();

            RolePermission::query()->where('role', $role->value)->delete();

            foreach ($grants as $key => $level) {
                RolePermission::query()->create([
                    'role' => $role->value,
                    'permission_key' => $key,
                    'level' => $level,
                ]);
            }

            $after = RolePermission::query()
                ->where('role', $role->value)
                ->pluck('level', 'permission_key')
                ->all();

            $this->audit->write($actor, 'rbac.role_matrix.update', 'RoleType', $role->value, [
                'levels' => $before,
            ], [
                'levels' => $after,
            ]);
        });

        $this->authorize->forgetMatrixCache();
    }

    /**
     * Replace one role's grants with the defaults from config/permissions.php.
     */
    public function resetRoleFromConfig(User $actor, RoleType $role): int
    {
        $this->authorize->authorize($actor, 'roles.manage_matrix');

        if ($role === RoleType::SuperAdmin) {
            abort(403);
        }

        $defaults = config('permissions', []);
        $written = 0;
        $before = RolePermission::query()
            ->where('role', $role->value)
            ->pluck('level', 'permission_key')
            ->all();

        DB::transaction(function () use ($actor, $role, $defaults, $before, &$written): void {
            RolePermission::query()->where('role', $role->value)->delete();

            foreach ($defaults as $permissionKey => $roleLevels) {
                if (! is_array($roleLevels) || ! array_key_exists($role->value, $roleLevels)) {
                    continue;
                }

                RolePermission::query()->create([
                    'role' => $role->value,
                    'permission_key' => $permissionKey,
                    'level' => (string) $roleLevels[$role->value],
                ]);
                $written++;
            }

            $after = RolePermission::query()
                ->where('role', $role->value)
                ->pluck('level', 'permission_key')
                ->all();

            $this->audit->write($actor, 'rbac.role_matrix.reset', 'RoleType', $role->value, [
                'permissions' => array_keys($before),
                'levels' => $before,
            ], [
                'permissions' => array_keys($after),
                'levels' => $after,
                'written' => $written,
            ]);
        });

        $this->authorize->forgetMatrixCache();

        return $written;
    }

    /**
     * @param  array<int|string, mixed>  $permissionLevels
     * @return array<string, string>
     */
    private function validatedGrants(array $permissionLevels): array
    {
        if ($permissionLevels !== [] && array_is_list($permissionLevels)) {
            throw ValidationException::withMessages([
                'permissions' => [__('roles_hub.level_required')],
            ]);
        }

        $knownKeys = array_fill_keys(array_keys(config('permissions', [])), true);
        $allowedLevels = array_fill_keys(self::GRANT_LEVELS, true);
        $grants = [];

        foreach ($permissionLevels as $key => $level) {
            if (! is_string($key) || $key === '') {
                throw ValidationException::withMessages([
                    'permissions' => [__('roles_hub.unknown_key', ['key' => (string) $key])],
                ]);
            }

            if (! isset($knownKeys[$key])) {
                throw ValidationException::withMessages([
                    "permissions.$key" => [__('roles_hub.unknown_key', ['key' => $key])],
                ]);
            }

            if ($level === null) {
                continue;
            }

            if (! is_string($level) && ! is_int($level)) {
                throw ValidationException::withMessages([
                    "permissions.$key" => [__('roles_hub.unknown_level', ['level' => get_debug_type($level)])],
                ]);
            }

            $level = trim((string) $level);
            if ($level === '') {
                continue;
            }

            if (! isset($allowedLevels[$level])) {
                throw ValidationException::withMessages([
                    "permissions.$key" => [__('roles_hub.unknown_level', ['level' => $level])],
                ]);
            }

            $grants[$key] = $level;
        }

        return $grants;
    }
}
