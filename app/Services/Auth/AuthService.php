<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\AuditLogWriter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @return array{user: User, otp: string}
     */
    public function register(array $data): array
    {
        $user = $this->audit->withAudit(
            null,
            'auth.register',
            fn () => User::query()->create([
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'status' => UserStatus::Pending,
                'email_verified' => false,
                'preferred_locale' => $data['preferred_locale'] ?? 'en',
            ]),
            'User'
        );

        $otp = $this->otp->issue($user, OtpPurpose::EmailVerification);

        return ['user' => $user, 'otp' => $otp];
    }

    public function verifyEmail(User $user, string $code): void
    {
        if (! $this->otp->verify($user, OtpPurpose::EmailVerification, $code)) {
            throw ValidationException::withMessages(['code' => [__('auth.otp_invalid')]]);
        }

        $user->update(['email_verified' => true]);

        $this->audit->write($user, 'auth.email_verified', 'User', $user->id);
    }

    public function setPassword(User $user, string $password): void
    {
        if (! $user->email_verified) {
            throw ValidationException::withMessages(['password' => [__('auth.email_not_verified')]]);
        }

        $user->update([
            'password_hash' => Hash::make($password),
            'status' => UserStatus::Active,
        ]);

        if ($user->roles()->count() === 0) {
            $user->roles()->create(['role' => \App\Enums\RoleType::Student]);
        }

        $this->audit->write($user, 'auth.password_set', 'User', $user->id);
    }

    public function login(string $email, string $password): User
    {
        $user = User::query()->where('email', strtolower($email))->first();

        if ($user === null || $user->password_hash === null || ! Hash::check($password, $user->password_hash)) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }

        if ($user->status === UserStatus::Suspended) {
            throw ValidationException::withMessages(['email' => [__('auth.suspended')]]);
        }

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages(['email' => [__('auth.not_active')]]);
        }

        Auth::login($user, false);
        $this->audit->write($user, 'auth.login', 'User', $user->id);

        return $user;
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user instanceof User) {
            $this->audit->write($user, 'auth.logout', 'User', $user->id);
        }
        Auth::logout();
    }

    public function requestPasswordReset(string $email): string
    {
        $user = User::query()->where('email', strtolower($email))->first();

        if ($user === null) {
            throw ValidationException::withMessages(['email' => [__('auth.reset_user_not_found')]]);
        }

        return $this->otp->issue($user, OtpPurpose::PasswordReset);
    }

    public function resetPassword(string $email, string $code, string $password): void
    {
        $user = User::query()->where('email', strtolower($email))->firstOrFail();

        if (! $this->otp->verify($user, OtpPurpose::PasswordReset, $code)) {
            throw ValidationException::withMessages(['code' => [__('auth.otp_invalid')]]);
        }

        $user->update(['password_hash' => Hash::make($password)]);
        $this->audit->write($user, 'auth.password_reset', 'User', $user->id);
    }
}
