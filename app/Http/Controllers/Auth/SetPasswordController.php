<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SetPasswordController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (! session('pending_user_id') || ! session('verified')) {
            return redirect()->route('auth.register');
        }

        return view('auth.set-password');
    }

    public function store(Request $request, AuthService $auth): RedirectResponse
    {
        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::query()->findOrFail(session('pending_user_id'));
        $auth->setPassword($user, $data['password']);
        $auth->login($user->email, $data['password']);

        session()->forget(['pending_user_id', 'verified', 'dev_otp']);

        return redirect()->route('dashboard');
    }
}
