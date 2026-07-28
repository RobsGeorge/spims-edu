<?php

namespace App\Services\Admin;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserRole;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserAdminService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function createUser(User $actor, array $data): User
    {
        $this->authorize->authorize($actor, 'users.manage');

        return $this->audit->withAudit($actor, 'users.create', function () use ($data, $actor) {
            $user = User::query()->create([
                'email' => strtolower($data['email']),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'password_hash' => isset($data['password']) ? Hash::make($data['password']) : null,
                'email_verified' => true,
                'status' => UserStatus::Active,
                'preferred_locale' => $data['preferred_locale'] ?? 'en',
                'is_reviewer' => (bool) ($data['is_reviewer'] ?? false),
            ]);

            foreach ($data['roles'] ?? [] as $roleValue) {
                $this->assignRole($actor, $user, RoleType::from($roleValue), skipAuth: true);
            }

            return $user->fresh('roles');
        }, 'User');
    }

    public function assignRole(User $actor, User $target, RoleType $role, bool $skipAuth = false): void
    {
        if (! $skipAuth) {
            $this->authorize->authorize($actor, 'roles.assign');
        }

        if (! $this->authorize->canAssignRole($actor, $role)) {
            throw ValidationException::withMessages(['role' => [__('auth.cannot_assign_role')]]);
        }

        UserRole::query()->updateOrCreate(
            ['user_id' => $target->id, 'role' => $role],
            ['role' => $role]
        );

        $this->audit->write($actor, 'roles.assign', 'User', $target->id, null, ['role' => $role->value]);
    }

    public function suspend(User $actor, User $target): void
    {
        $this->authorize->authorize($actor, 'users.manage');
        $before = $target->only(['status']);
        $target->update(['status' => UserStatus::Suspended]);
        $this->audit->write($actor, 'users.suspend', 'User', $target->id, $before, $target->only(['status']));
    }
}
