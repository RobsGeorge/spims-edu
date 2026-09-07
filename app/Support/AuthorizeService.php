<?php

namespace App\Support;

use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\Scope\ResourceScopeResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class AuthorizeService
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $dbMatrix = null;

    private static bool $dbChecked = false;

    public function __construct(
        private readonly ResourceScopeResolver $scope = new ResourceScopeResolver,
    ) {}

    public function allows(?User $user, string $action, mixed $resource = null): bool
    {
        try {
            $this->authorize($user, $action, $resource);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function authorize(?User $user, string $action, mixed $resource = null): void
    {
        if ($user === null) {
            throw new AuthorizationException(__('auth.unauthorized'));
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        $scopedGrant = false;
        $granted = false;

        foreach ($user->roleTypes() as $role) {
            $level = $this->levelFor($role, $action);

            if ($level === null || $level === '' || ! $this->levelGrants($level)) {
                continue;
            }

            $granted = true;

            // An unscoped grant from any one role wins outright: an Academic Admin who
            // also teaches is not confined to the offerings they teach.
            if (! $this->isScopedRole($role)) {
                return;
            }

            $scopedGrant = true;
        }

        if (! $granted) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        if (! $scopedGrant || ! $this->isOfferingScoped($action)) {
            return;
        }

        // Advising keys (`advising.assign` / `advising.hold` / `advising.view`)
        // are deliberately not offering-scoped. Instructor "O" means assigned
        // advisee only and is enforced in AdvisingService, which fails closed
        // if no student resource is passed. Do not add those keys to
        // permission_scopes.offering_scoped.

        // Fail closed. A scoped action reached without a resource is a missing argument
        // at the call site, not a permission the actor happens to hold everywhere.
        if ($resource === null || ! $this->scope->scopedTo($user, $resource)) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }

    private function levelGrants(string $level): bool
    {
        return $level === 'F'
            || $level === 'R'
            || str_contains($level, 'O')
            || in_array($level, ['submit', 'lock', 'reopen', 'issue'], true);
    }

    private function isScopedRole(RoleType $role): bool
    {
        return in_array($role->value, (array) config('permission_scopes.scoped_roles', []), true);
    }

    private function isOfferingScoped(string $action): bool
    {
        return in_array($action, (array) config('permission_scopes.offering_scoped', []), true);
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
