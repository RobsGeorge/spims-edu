<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ThemePreference;
use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuthService $auth): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = $auth->login($credentials['email'], $credentials['password']);

        $redirect = redirect()->intended(route('dashboard'));

        if (! $request->hasCookie('theme')) {
            $cookieTheme = match ($user->theme_preference) {
                ThemePreference::Dark => 'dark',
                ThemePreference::Light => 'light',
                default => 'light',
            };
            $redirect->withCookie(cookie('theme', $cookieTheme, 60 * 24 * 365));
        }

        return $redirect;
    }

    public function destroy(AuthService $auth): RedirectResponse
    {
        $auth->logout();

        return redirect()->route('home');
    }
}
