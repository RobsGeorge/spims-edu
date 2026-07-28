<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, AuthService $auth): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|unique:users,email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'preferred_locale' => 'nullable|in:ar,en,fr',
        ]);

        $result = $auth->register($data);

        session([
            'pending_user_id' => $result['user']->id,
            'dev_otp' => app()->environment(['local', 'testing']) ? $result['otp'] : null,
        ]);

        return redirect()->route('auth.verify')->with('status', __('auth.otp_sent_log'));
    }
}
