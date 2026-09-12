<?php

namespace App\Providers;

use App\Models\Theme;
use App\Models\User;
use App\Observers\UserObserver;
use App\Services\Ai\AiClient;
use App\Services\Ai\GeminiAiClient;
use App\Services\Communications\AnnouncementService;
use App\Services\SuperAdmin\ImpersonationService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use App\Support\ThemeTokens;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthorizeService::class);
        $this->app->singleton(AuditLogWriter::class);
        $this->app->singleton(AiClient::class, GeminiAiClient::class);
    }

    public function boot(): void
    {
        // L5 — flips import_account_claims to CLAIMED on a genuine password-hash set;
        // see docs/legacy-data-import-plan.md §9 and app/Observers/UserObserver.php.
        User::observe(UserObserver::class);

        View::composer('layouts.app', function ($view): void {
            $cookieTheme = request()->cookie('theme', 'light');
            if (! in_array($cookieTheme, ['light', 'dark', 'system'], true)) {
                $cookieTheme = 'light';
            }

            $activeTheme = Theme::query()->where('is_active', true)->first();
            $locale = app()->getLocale();
            $isRtl = $locale === 'ar';

            $activeBanner = null;
            $user = auth()->user();
            if ($user && Schema::hasTable('announcement_deliveries')) {
                try {
                    $activeBanner = app(AnnouncementService::class)->inboxFor($user, bannersOnly: true)->first();
                } catch (\Throwable) {
                    $activeBanner = null;
                }
            }

            $impersonatorId = session(ImpersonationService::SESSION_KEY);
            $impersonator = is_string($impersonatorId) && $impersonatorId !== ''
                ? User::query()->find($impersonatorId)
                : null;

            $view->with([
                'activeTheme' => $activeTheme,
                'cookieTheme' => $cookieTheme,
                'themeCssBlock' => ThemeTokens::inlineStyleBlock($activeTheme?->tokens),
                'isRtl' => $isRtl,
                'localeDir' => $isRtl ? 'rtl' : 'ltr',
                'activeBanner' => $activeBanner,
                'impersonator' => $impersonator,
            ]);
        });
    }
}
