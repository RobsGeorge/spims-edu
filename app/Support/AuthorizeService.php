<?php

namespace App\Support;

use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use Illuminate\Support\Arr;

class AuthorizeService
{
    public function authorize(?User $user, string $action, mixed $resource = null): void
    {
        if ($user === null) {
            throw new AuthorizationException(__('auth.unauthorized'));
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        $matrix = Arr::get(config('permissions'), $action);

        if ($matrix === null) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        $allowed = false;

        foreach ($user->roleTypes() as $role) {
            $level = Arr::get($matrix, $role->value);

            if ($level === null) {
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
}
