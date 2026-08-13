<?php

namespace App\Http\Controllers;

use App\Enums\ThemePreference;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(
        Request $request,
        AuthorizeService $authorize,
        AuditLogWriter $audit
    ): RedirectResponse {
        $authorize->authorize($request->user(), 'profile.edit_own');

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:40',
            'preferred_locale' => 'required|in:ar,en,fr',
            'theme_preference' => 'required|in:LIGHT,DARK,SYSTEM',
            'notify_email' => 'nullable|boolean',
        ]);

        $user = $request->user();

        $audit->withAudit($user, 'profile.update', function () use ($user, $data) {
            $user->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'preferred_locale' => $data['preferred_locale'],
                'theme_preference' => ThemePreference::from($data['theme_preference']),
                'notify_email' => (bool) ($data['notify_email'] ?? false),
            ]);

            return $user->fresh();
        }, 'User');

        $themeCookie = match ($data['theme_preference']) {
            'LIGHT' => 'light',
            'DARK' => 'dark',
            default => 'system',
        };

        return back()
            ->with('status', __('learning.profile_saved'))
            ->withCookie(cookie('locale', $data['preferred_locale'], 60 * 24 * 365))
            ->withCookie(cookie('theme', $themeCookie, 60 * 24 * 365));
    }
}
