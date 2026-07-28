<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use Illuminate\Http\JsonResponse;

class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        $theme = Theme::query()->where('is_active', true)->first();

        if ($theme === null) {
            return response()->json(['siteName' => 'SPIMS', 'tokens' => []]);
        }

        return response()->json([
            'siteName' => $theme->site_name,
            'logoLightUrl' => $theme->logo_light_url,
            'logoDarkUrl' => $theme->logo_dark_url,
            'faviconUrl' => $theme->favicon_url,
            'tokens' => $theme->tokens,
        ]);
    }
}
