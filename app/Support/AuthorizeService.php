<?php

namespace App\Support;

use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class AuthorizeService
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $dbMatrix = null;

    private static bool $dbChecked = false;

    public function authorize(?User $user, string $action, mixed $resource = null): void
    {
        if ($user === null) {
            throw new AuthorizationException(__('auth.unauthorized'));
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        $allowed = false;

        foreach ($user->roleTypes() as $role) {
            $level = $this->levelFor($role, $action);

            if ($level === null || $level === '') {
                continue;
            }

            if ($level === 'F' || $level === 'R' || str_contains($level, 'O')) {
                $allowed = true;
                break;
            }

            if (in_array($level, ['submit', 'lock', 'reopen', 'issue'], true)) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }

    public function canAssignRole(User $actor, RoleType $roleToAssign): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if (in_array($roleToAssign, [RoleType::SuperAdmin, RoleType::AdministrativeAdmin], true)) {
            return false;
        }

        try {
            $this->authorize($actor, 'roles.assign');

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function forgetMatrixCache(): void
    {
        self::$dbMatrix = null;
        self::$dbChecked = false;
    }

    private function levelFor(RoleType $role, string $action): ?string
    {
        $matrix = $this->dbMatrix();

        if ($matrix !== null) {
            return $matrix[$action][$role->value] ?? null;
        }

        $configRow = Arr::get(config('permissions'), $action);
        if ($configRow === null) {
            return null;
        }

        return Arr::get($configRow, $role->value);
    }

    /**
     * @return array<string, array<string, string>>|null
     */
    private function dbMatrix(): ?array
    {
        if (self::$dbChecked) {
            return self::$dbMatrix;
        }

        self::$dbChecked = true;

        if (! Schema::hasTable('role_permissions')) {
            self::$dbMatrix = null;

            return null;
        }

        if (! RolePermission::query()->exists()) {
            self::$dbMatrix = null;

            return null;
        }

        $map = [];
        foreach (RolePermission::query()->get(['role', 'permission_key', 'level']) as $row) {
            $map[$row->permission_key][$row->role->value] = $row->level;
        }

        self::$dbMatrix = $map;

        return self::$dbMatrix;
    }
}
