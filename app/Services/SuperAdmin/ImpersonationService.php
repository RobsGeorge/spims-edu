<?php

namespace App\Services\SuperAdmin;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';

    public const STARTED_AT_KEY = 'impersonator_started_at';

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly AuthService $auth,
    ) {}

    public function start(User $actor, User $target): void
    {
        $this->authorize->authorize($actor, 'users.impersonate');

        if ($this->impersonatorId() !== null) {
            throw ValidationException::withMessages(['user' => [__('people.impersonate_already')]]);
        }

        if ($actor->is($target)) {
            throw ValidationException::withMessages(['user' => [__('people.impersonate_blocked_self')]]);
        }

        if ($target->isSuperAdmin()) {
            throw ValidationException::withMessages(['user' => [__('people.impersonate_blocked_superadmin')]]);
        }

        if ($target->status !== UserStatus::Active) {
            throw ValidationException::withMessages(['user' => [__('people.impersonate_blocked_status')]]);
        }

        $actorId = $actor->id;
        $this->auth->loginAs($target);
        session()->put(self::SESSION_KEY, $actorId);
        session()->put(self::STARTED_AT_KEY, now()->toIso8601String());

        $this->audit->write($actor, 'users.impersonate.start', 'User', $target->id, null, [
            'target_email' => $target->email,
            'target_name' => $target->displayName(),
        ]);
    }

    public function stop(): User
    {
        $impersonatorId = $this->impersonatorId();
        if ($impersonatorId === null) {
            throw ValidationException::withMessages(['user' => [__('people.impersonate_not_active')]]);
        }

        $impersonator = User::query()->find($impersonatorId);
        if ($impersonator === null) {
            session()->forget([self::SESSION_KEY, self::STARTED_AT_KEY]);
            throw ValidationException::withMessages(['user' => [__('people.impersonate_not_active')]]);
        }

        $target = Auth::user();
        Auth::login($impersonator, false);
        session()->forget([self::SESSION_KEY, self::STARTED_AT_KEY]);

        $this->audit->write($impersonator, 'users.impersonate.stop', 'User', $target instanceof User ? $target->id : null);

        return $impersonator;
    }

    public function impersonatorId(): ?string
    {
        $id = session(self::SESSION_KEY);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function impersonator(): ?User
    {
        $id = $this->impersonatorId();

        return $id === null ? null : User::query()->find($id);
    }
}
