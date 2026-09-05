<?php

namespace App\Support\Api;

use Illuminate\Http\Request;

/**
 * Locale resolution as a pure function of the request, usable both from
 * `SetApiLocale` (for the happy path) and directly from exception rendering.
 *
 * It must be usable directly from exception rendering rather than only via the
 * middleware side-effect: Laravel's global middleware-priority list runs
 * `Authenticate` before ordinary route middleware regardless of registration
 * order, so an unauthenticated request's 401 can fire before `SetApiLocale` — a
 * middleware that "sets the locale" is not a substitute for resolving it at the
 * point a translated string is actually produced.
 */
final class ApiLocale
{
    private const SUPPORTED = ['ar', 'en', 'fr'];

    public static function resolve(Request $request): string
    {
        foreach (self::acceptLanguageCandidates($request) as $candidate) {
            if (in_array($candidate, self::SUPPORTED, true)) {
                return $candidate;
            }
        }

        $preferred = $request->user()?->preferred_locale;
        if (is_string($preferred) && in_array($preferred, self::SUPPORTED, true)) {
            return $preferred;
        }

        return 'en';
    }

    /** @return array<int, string> */
    private static function acceptLanguageCandidates(Request $request): array
    {
        $header = $request->header('Accept-Language');
        if (! is_string($header) || $header === '') {
            return [];
        }

        // "ar;q=0.9, en-US;q=0.8" -> ["ar", "en"], preserving priority order.
        return array_map(
            fn (string $tag) => strtolower(substr(trim(explode(';', $tag)[0]), 0, 2)),
            explode(',', $header)
        );
    }
}
