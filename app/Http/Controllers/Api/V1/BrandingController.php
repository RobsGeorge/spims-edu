<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Support\ThemeTokens;
use Illuminate\Http\JsonResponse;

/**
 * Versioned counterpart to the legacy `GET /api/branding` (unauthenticated, used by
 * the web shell before login). Same data, `data`-wrapped envelope.
 */
class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        $theme = Theme::query()->where('is_active', true)->first();

        if ($theme === null) {
            return response()->json([
                'data' => [
                    'site_name' => 'SPIMS',
                    'logo_light_url' => null,
                    'logo_dark_url' => null,
                    'favicon_url' => null,
                    'tokens' => ThemeTokens::defaults(),
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'site_name' => $theme->site_name,
                'logo_light_url' => $theme->logo_light_url,
                'logo_dark_url' => $theme->logo_dark_url,
                'favicon_url' => $theme->favicon_url,
                'tokens' => ThemeTokens::resolve($theme->tokens),
            ],
        ]);
    }
}
