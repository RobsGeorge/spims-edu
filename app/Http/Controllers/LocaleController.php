<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => 'required|in:ar,en,fr',
        ]);

        if ($request->user()) {
            $request->user()->update(['preferred_locale' => $validated['locale']]);
        }

        return back()->withCookie(cookie('locale', $validated['locale'], 60 * 24 * 365));
    }
}
