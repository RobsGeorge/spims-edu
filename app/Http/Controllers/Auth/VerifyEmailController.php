<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerifyEmailController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (! session('pending_user_id')) {
            return redirect()->route('auth.register');
        }

        return view('auth.verify', ['devOtp' => session('dev_otp')]);
    }

    public function store(Request $request, AuthService $auth): RedirectResponse
    {
        $data = $request->validate(['code' => 'required|string|size:6']);
        $user = User::query()->findOrFail(session('pending_user_id'));

        $auth->verifyEmail($user, $data['code']);

        session(['pending_user_id' => $user->id, 'verified' => true]);

        return redirect()->route('auth.password.create');
    }
}
