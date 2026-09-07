<?php

namespace App\Providers;

use App\Models\Theme;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\GeminiAiClient;
use App\Services\Communications\AnnouncementService;
use App\Services\SuperAdmin\FeatureFlagService;
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
        $this->app->singleton(FeatureFlagService::class);
        $this->app->singleton(SystemSettingService::class);
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $cookieTheme = request()->cookie('theme', 'system');
            if (! in_array($cookieTheme, ['light', 'dark', 'system'], true)) {
                $cookieTheme = 'system';
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

            $view->with(array_merge([
                'activeTheme' => $activeTheme,
                'cookieTheme' => $cookieTheme,
                'themeCssBlock' => ThemeTokens::inlineStyleBlock($activeTheme?->tokens),
                'isRtl' => $isRtl,
                'localeDir' => $isRtl ? 'rtl' : 'ltr',
                'activeBanner' => $activeBanner,
                'impersonator' => $impersonator,
            ], $this->featureFlagViewData()));
        });

        View::composer(['home', 'auth.login', 'dashboard'], function ($view): void {
            $view->with($this->featureFlagViewData());
        });
    }

    /**
     * @return array<string, bool>
     */
    private function featureFlagViewData(): array
    {
        $flags = app(FeatureFlagService::class);

        return [
            'featurePublicCatalog' => $flags->enabled('public_catalog'),
            'featureRegistration' => $flags->enabled('registration'),
            'featureLearn' => $flags->enabled('learn'),
            'featureEvents' => $flags->enabled('events'),
            'featureSurveys' => $flags->enabled('surveys'),
            'featureLive' => $flags->enabled('live'),
        ];
    }
}
