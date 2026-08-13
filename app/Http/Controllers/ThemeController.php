<?php

namespace App\Http\Controllers;

use App\Enums\ThemePreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => 'required|in:light,dark,system',
        ]);

        if ($request->user()) {
            $request->user()->update([
                'theme_preference' => match ($validated['theme']) {
                    'light' => ThemePreference::Light,
                    'dark' => ThemePreference::Dark,
                    default => ThemePreference::System,
                },
            ]);
        }

        return back()->withCookie(cookie('theme', $validated['theme'], 60 * 24 * 365));
    }
}
