<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Services\Admin\ThemeAdminService;
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
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'site_name' => 'required|string|max:150',
            'logo_light_url' => 'nullable|url',
            'logo_dark_url' => 'nullable|url',
            'favicon_url' => 'nullable|url',
            'is_active' => 'boolean',
            'tokens' => 'nullable|array',
        ]);

        $service->update($request->user(), $theme, $data);

        return back()->with('status', __('ui.theme_saved'));
    }
}
