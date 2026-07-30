<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Services\Admin\ThemeAdminService;
use App\Support\ThemeTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeEditorController extends Controller
{
    public function edit(): View
    {
        $theme = Theme::query()->where('is_active', true)->first()
            ?? Theme::query()->firstOrFail();

        return view('admin.theme.edit', ['theme' => $theme]);
    }

    public function update(Request $request, Theme $theme, ThemeAdminService $service): RedirectResponse
    {
        foreach (['logo_light_url', 'logo_dark_url', 'favicon_url'] as $urlField) {
            if ($request->input($urlField) === '') {
                $request->merge([$urlField => null]);
            }
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'site_name' => 'required|string|max:150',
            'logo_light_url' => 'nullable|url|max:500',
            'logo_dark_url' => 'nullable|url|max:500',
            'favicon_url' => 'nullable|url|max:500',
            'is_active' => 'boolean',
            'tokens' => 'nullable|array',
            'tokens.light' => 'nullable|array',
            'tokens.dark' => 'nullable|array',
            'tokens.light.primary' => 'nullable|string|max:32',
            'tokens.light.bg1' => 'nullable|string|max:32',
            'tokens.light.accent' => 'nullable|string|max:32',
            'tokens.dark.primary' => 'nullable|string|max:32',
            'tokens.dark.bg1' => 'nullable|string|max:32',
            'tokens.dark.accent' => 'nullable|string|max:32',
        ]);

        if (isset($data['tokens']) && is_array($data['tokens'])) {
            $data['tokens'] = ThemeTokens::resolve(
                array_replace_recursive($theme->tokens ?? [], $data['tokens'])
            );
        }

        $service->update($request->user(), $theme, $data);

        return back()->with('status', __('ui.theme_saved'));
    }
}
