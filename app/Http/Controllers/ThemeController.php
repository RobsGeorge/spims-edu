<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => 'required|in:light,dark,system',
        ]);

        return back()->withCookie(cookie('theme', $validated['theme'], 60 * 24 * 365));
    }
}
