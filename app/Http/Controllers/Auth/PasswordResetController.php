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
            'reset_verified' => false,
            'dev_otp' => app()->environment(['local', 'testing']) ? $otp : null,
        ]);

        return redirect()->route('auth.password.verify.form');
    }

    public function verifyOtpForm(): View|RedirectResponse
    {
        if (! session('reset_email')) {
            return redirect()->route('auth.password.request');
        }

        return view('auth.reset-verify', ['devOtp' => session('dev_otp')]);
    }

    public function verifyOtp(Request $request, AuthService $auth): RedirectResponse
    {
        if (! session('reset_email')) {
            return redirect()->route('auth.password.request');
        }

        $data = $request->validate(['code' => 'required|string|size:6']);
        $auth->verifyPasswordResetOtp(session('reset_email'), $data['code']);

        session(['reset_verified' => true, 'dev_otp' => null]);

        return redirect()->route('auth.password.reset.form');
    }

    public function resendOtp(AuthService $auth): RedirectResponse
    {
        if (! session('reset_email')) {
            return redirect()->route('auth.password.request');
        }

        $otp = $auth->requestPasswordReset(session('reset_email'));
        session(['dev_otp' => app()->environment(['local', 'testing']) ? $otp : null]);

        return redirect()->route('auth.password.verify.form')->with('status', __('auth.otp_resent'));
    }

    public function resetForm(): View|RedirectResponse
    {
        if (! session('reset_email')) {
            return redirect()->route('auth.password.request');
        }

        if (! session('reset_verified')) {
            return redirect()->route('auth.password.verify.form');
        }

        return view('auth.reset-password');
    }

    public function reset(Request $request, AuthService $auth): RedirectResponse
    {
        if (! session('reset_email') || ! session('reset_verified')) {
            return redirect()->route('auth.password.request');
        }

        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $auth->resetPasswordAfterVerified(session('reset_email'), $data['password']);
        session()->forget(['reset_email', 'dev_otp', 'reset_verified']);

        return redirect()->route('auth.login')->with('status', __('auth.password_reset_success'));
    }
}
