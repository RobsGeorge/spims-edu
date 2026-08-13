<?php

namespace App\Providers;

use App\Models\Theme;
use App\Services\Ai\AiClient;
use App\Services\Ai\GeminiAiClient;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use App\Support\ThemeTokens;
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
        View::composer('layouts.app', function ($view): void {
            $cookieTheme = request()->cookie('theme', 'system');
            if (! in_array($cookieTheme, ['light', 'dark', 'system'], true)) {
                $cookieTheme = 'system';
            }

            $activeTheme = Theme::query()->where('is_active', true)->first();
            $locale = app()->getLocale();
            $isRtl = $locale === 'ar';

            $view->with([
                'activeTheme' => $activeTheme,
                'cookieTheme' => $cookieTheme,
                'themeCssBlock' => ThemeTokens::inlineStyleBlock($activeTheme?->tokens),
                'isRtl' => $isRtl,
                'localeDir' => $isRtl ? 'rtl' : 'ltr',
            ]);
        });
    }
}
