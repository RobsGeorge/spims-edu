<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Services\Admin\ThemeAdminService;
use App\Support\AuthorizeService;
use App\Support\ThemeTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeStudioController extends Controller
{
    public function index(Request $request, ThemeAdminService $themes, AuthorizeService $authorize): View
    {
        $authorize->authorize($request->user(), 'theme.manage');

        return view('superadmin.theme.index', [
            'themes' => $themes->catalog(),
            'defaults' => ThemeTokens::defaults(),
        ]);
    }

    public function store(Request $request, ThemeAdminService $themes): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $theme = $themes->createFromDefaults($request->user(), $data['name']);

        return redirect()
            ->route('superadmin.theme.edit', $theme)
            ->with('status', __('theme_studio.created'));
    }

    public function edit(Request $request, Theme $theme, AuthorizeService $authorize): View
    {
        $authorize->authorize($request->user(), 'theme.manage');

        $tokens = ThemeTokens::resolve($theme->tokens);

        return view('superadmin.theme.edit', [
            'theme' => $theme,
            'tokens' => $tokens,
            'groups' => ThemeTokens::groups(),
            'cssMap' => ThemeTokens::cssPropertyMap(),
            'lightCss' => ThemeTokens::toCssVariables($tokens['light']),
            'darkCss' => ThemeTokens::toCssVariables($tokens['dark']),
        ]);
    }

    public function update(Request $request, Theme $theme, ThemeAdminService $themes): RedirectResponse
    {
        foreach (['logo_light_url', 'logo_dark_url', 'favicon_url'] as $urlField) {
            if ($request->input($urlField) === '') {
                $request->merge([$urlField => null]);
            }
        }

        $rules = [
            'name' => 'required|string|max:100',
            'site_name' => 'required|string|max:150',
            'logo_light_url' => 'nullable|string|max:500',
            'logo_dark_url' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
            'tokens' => 'nullable|array',
            'tokens.light' => 'nullable|array',
            'tokens.dark' => 'nullable|array',
        ];
        foreach (ThemeTokens::keys() as $key) {
            $rules['tokens.light.'.$key] = 'nullable|string|max:120';
            $rules['tokens.dark.'.$key] = 'nullable|string|max:120';
        }

        $data = $request->validate($rules);
        unset($data['is_active']);
        $themes->update($request->user(), $theme, $data);

        return back()->with('status', __('theme_studio.saved'));
    }

    public function duplicate(Request $request, Theme $theme, ThemeAdminService $themes): RedirectResponse
    {
        $copy = $themes->duplicate($request->user(), $theme);

        return redirect()
            ->route('superadmin.theme.edit', $copy)
            ->with('status', __('theme_studio.duplicated'));
    }

    public function activate(Request $request, Theme $theme, ThemeAdminService $themes): RedirectResponse
    {
        $themes->activate($request->user(), $theme);

        return back()->with('status', __('theme_studio.activated', ['name' => $theme->fresh()?->name ?? $theme->name]));
    }

    public function reset(Request $request, Theme $theme, ThemeAdminService $themes): RedirectResponse
    {
        $themes->resetTokens($request->user(), $theme);

        return back()->with('status', __('theme_studio.reset_done'));
    }

    public function asset(Request $request, Theme $theme, ThemeAdminService $themes): RedirectResponse
    {
        $data = $request->validate([
            'field' => 'required|in:logo_light_url,logo_dark_url,favicon_url',
            'file' => ['required', 'file', 'max:2048', 'mimes:jpeg,jpg,png,gif,webp'],
        ]);

        $themes->storeAsset($request->user(), $theme, $data['field'], $request->file('file'));

        return back()->with('status', __('theme_studio.asset_saved'));
    }
}
