<?php

namespace App\Providers;

use App\Models\Theme;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthorizeService::class);
        $this->app->singleton(AuditLogWriter::class);
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $cookieTheme = request()->cookie('theme', 'system');
            $activeTheme = Theme::query()->where('is_active', true)->first();
            $locale = app()->getLocale();
            $isRtl = $locale === 'ar';

            $view->with([
                'activeTheme' => $activeTheme,
                'cookieTheme' => $cookieTheme,
                'isRtl' => $isRtl,
                'localeDir' => $isRtl ? 'rtl' : 'ltr',
            ]);
        });
    }
}
