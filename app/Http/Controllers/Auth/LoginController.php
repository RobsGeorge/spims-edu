<?php

namespace App\Http\Controllers\Auth;

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

        $auth->login($credentials['email'], $credentials['password']);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(AuthService $auth): RedirectResponse
    {
        $auth->logout();

        return redirect()->route('home');
    }
}
