<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request, AuthService $auth): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email']);
        $otp = $auth->requestPasswordReset($data['email']);

        session([
            'reset_email' => strtolower($data['email']),
            'dev_otp' => app()->environment(['local', 'testing']) ? $otp : null,
        ]);

        return redirect()->route('auth.password.reset.form')->with('status', __('auth.otp_sent_log'));
    }

    public function resetForm(): View|RedirectResponse
    {
        if (! session('reset_email')) {
            return redirect()->route('auth.password.request');
        }

        return view('auth.reset-password', ['devOtp' => session('dev_otp')]);
    }

    public function reset(Request $request, AuthService $auth): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $auth->resetPassword(session('reset_email'), $data['code'], $data['password']);
        session()->forget(['reset_email', 'dev_otp']);

        return redirect()->route('auth.login')->with('status', __('auth.password_reset_success'));
    }
}
