<?php

namespace App\Services\Demo;

use App\Enums\RoleType;
use App\Models\GradingScheme;
use App\Models\Setting;
use App\Models\Theme;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Auth\AuthService;
use App\Support\AuditLogWriter;
use App\Support\DemoPersonas;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\GradingSchemeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Database\Seeders\ThemeSeeder;
use Illuminate\Support\Facades\Auth;

class DemoConsoleService
{
    public function __construct(
        private readonly AuditLogWriter $audit,
        private readonly AuthService $auth,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('spims.demo_console', false);
    }

    public function refresh(?User $actor): void
    {
        $this->audit->withAudit(
            $actor,
            'demo.refresh',
            function () {
                $previous = config('spims.seed_demo_data');
                config(['spims.seed_demo_data' => true]);

                try {
                    $this->ensureFoundation();
                    app(DemoDataSeeder::class)->run();
                } finally {
                    config(['spims.seed_demo_data' => $previous]);
                }

                return null;
            },
            'Demo'
        );
    }

    public function enter(string $slug, ?User $current): User
    {
        $persona = DemoPersonas::find($slug);
        if ($persona === null) {
            abort(404);
        }

        $this->assertSafeEmail($persona['email']);

        $user = User::query()->where('email', $persona['email'])->first();
        if ($user === null) {
            $this->refresh($current);
            $user = User::query()->where('email', $persona['email'])->firstOrFail();
        }

        $this->assertSafeUser($user);

        if (Auth::check() && Auth::id() !== $user->id) {
            $this->auth->logout();
        }

        if (! Auth::check()) {
            $this->auth->loginAs($user, 'auth.demo_enter');
        }

        return $user->fresh(['roles']) ?? $user;
    }

    private function ensureFoundation(): void
    {
        app(LanguageSeeder::class)->run();

        if (! GradingScheme::query()->where('is_default', true)->exists()) {
            app(GradingSchemeSeeder::class)->run();
        }

        if (! Setting::query()->exists()) {
            app(SettingsSeeder::class)->run();
        }

        if (! Theme::query()->exists()) {
            app(ThemeSeeder::class)->run();
        }

        app(RolePermissionSeeder::class)->run();

        if (! UserRole::query()->where('role', RoleType::SuperAdmin)->exists()) {
            app(SuperAdminSeeder::class)->run();
        }
    }

    private function assertSafeEmail(string $email): void
    {
        $email = strtolower($email);
        $super = strtolower((string) env('SUPERADMIN_EMAIL', 'robeir.george@outlook.com'));

        if (! str_ends_with($email, '@spims.test') || $email === $super) {
            abort(404);
        }
    }

    private function assertSafeUser(User $user): void
    {
        $user->loadMissing('roles');

        if ($user->isSuperAdmin() || ! str_ends_with(strtolower((string) $user->email), '@spims.test')) {
            abort(404);
        }
    }
}
