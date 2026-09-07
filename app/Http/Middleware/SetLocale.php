<?php

namespace App\Http\Middleware;

use App\Services\SuperAdmin\SystemSettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $schoolLocale = null;
        try {
            $schoolLocale = app(SystemSettingService::class)->value('school.default_locale');
        } catch (\Throwable) {
            $schoolLocale = null;
        }

        $locale = $request->route('locale')
            ?? $request->user()?->preferred_locale
            ?? $request->cookie('locale')
            ?? (is_string($schoolLocale) && in_array($schoolLocale, ['ar', 'en', 'fr'], true) ? $schoolLocale : null)
            ?? config('app.locale', 'en');

        if (! in_array($locale, ['ar', 'en', 'fr'], true)) {
            $locale = 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
