<?php

namespace App\Services\Admin;

use App\Enums\OtpPurpose;
use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Auth\OtpService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserAdminService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly OtpService $otp,
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

            if ($user->roles()->count() === 0) {
                $this->assignRole($actor, $user, RoleType::Student, skipAuth: true);
            }

            return $user->fresh('roles');
        }, 'User');
    }

    public function updateUser(User $actor, User $target, array $data): User
    {
        $this->authorize->authorize($actor, 'users.manage');

        return $this->audit->withAudit($actor, 'users.update', function () use ($target, $data) {
            $target->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'preferred_locale' => $data['preferred_locale'] ?? $target->preferred_locale,
                'country_code' => $data['country_code'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'notify_email' => (bool) ($data['notify_email'] ?? false),
                'is_reviewer' => (bool) ($data['is_reviewer'] ?? false),
            ]);

            return $target->fresh();
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

    public function update(User $actor, User $target, array $data): User
    {
        return $this->updateUser($actor, $target, $data);
    }

    public function removeRole(User $actor, User $target, RoleType $role): void
    {
        $this->revokeRole($actor, $target, $role);
    }

    public function suspend(User $actor, User $target): void
    {
        $this->authorize->authorize($actor, 'users.manage');
        $this->guardProtectedTarget($actor, $target, 'suspend');

        $before = $target->only(['status']);
        $target->update(['status' => UserStatus::Suspended]);
        $this->audit->write($actor, 'users.suspend', 'User', $target->id, $before, $target->only(['status']));
    }

    public function unsuspend(User $actor, User $target): void
    {
        $this->authorize->authorize($actor, 'users.unsuspend');

        if ($target->status !== UserStatus::Suspended) {
            throw ValidationException::withMessages(['status' => [__('people.not_suspended')]]);
        }

        $before = $target->only(['status']);
        $target->update(['status' => UserStatus::Active]);
        $this->audit->write($actor, 'users.unsuspend', 'User', $target->id, $before, $target->only(['status']));
    }

    public function forceActivate(User $actor, User $target): void
    {
        $this->authorize->authorize($actor, 'users.manage');
        $this->guardProtectedTarget($actor, $target, 'activate');

        if ($target->status !== UserStatus::Pending) {
            throw ValidationException::withMessages(['status' => [__('people.not_pending')]]);
        }

        $before = $target->only(['status', 'email_verified']);
        $target->update([
            'status' => UserStatus::Active,
            'email_verified' => true,
        ]);

        if ($target->roles()->count() === 0) {
            $this->assignRole($actor, $target, RoleType::Student, skipAuth: true);
        }

        $this->audit->write($actor, 'users.force_activate', 'User', $target->id, $before, $target->fresh()->only(['status', 'email_verified']));
    }

    public function revokeRole(User $actor, User $target, RoleType $role): void
    {
        $this->authorize->authorize($actor, 'roles.assign');

        if ($role === RoleType::SuperAdmin) {
            throw ValidationException::withMessages(['role' => [__('people.cannot_revoke_superadmin')]]);
        }

        if (! $this->authorize->canAssignRole($actor, $role)) {
            throw ValidationException::withMessages(['role' => [__('auth.cannot_assign_role')]]);
        }

        $deleted = UserRole::query()
            ->where('user_id', $target->id)
            ->where('role', $role)
            ->delete();

        if ($deleted === 0) {
            throw ValidationException::withMessages(['role' => [__('people.role_not_assigned')]]);
        }

        $this->audit->write($actor, 'roles.revoke', 'User', $target->id, ['role' => $role->value], null);

        $target->unsetRelation('roles');
        if ($target->roles()->count() === 0) {
            $this->assignRole($actor, $target, RoleType::Student, skipAuth: true);
            $this->audit->write($actor, 'roles.assign_default_student', 'User', $target->id, null, [
                'reason' => 'zero_roles',
            ]);
        }
    }

    public function issuePasswordReset(User $actor, User $target): string
    {
        $this->authorize->authorize($actor, 'users.reset_password');
        $this->guardProtectedTarget($actor, $target, 'password-reset');

        $otp = $this->otp->issue($target, OtpPurpose::PasswordReset);
        $this->audit->write($actor, 'users.password_reset_issued', 'User', $target->id, null, [
            'purpose' => OtpPurpose::PasswordReset->value,
        ]);

        return $otp;
    }

    public function revokeIdentitySessions(User $actor, User $target): int
    {
        $this->authorize->authorize($actor, 'users.manage');

        $count = $target->identitySessions()->count();
        $target->identitySessions()->delete();
        $this->audit->write($actor, 'users.sessions.revoke', 'User', $target->id, null, [
            'revoked' => $count,
        ]);

        return $count;
    }

    private function guardProtectedTarget(User $actor, User $target, string $action): void
    {
        if ($actor->is($target) && $action === 'suspend') {
            throw ValidationException::withMessages(['user' => [__('people.cannot_suspend_self')]]);
        }

        if ($target->isSeededSuperAdmin()) {
            throw ValidationException::withMessages(['user' => [__('people.cannot_mutate_seeded_superadmin')]]);
        }
    }
}
