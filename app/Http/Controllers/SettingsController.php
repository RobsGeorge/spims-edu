<?php

namespace App\Http\Controllers;

use App\Enums\ThemePreference;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request, ObjectStorageService $storage): View
    {
        $user = $request->user();

        return view('settings.edit', [
            'user' => $user,
            'avatarUrl' => filled($user?->avatar_path)
                ? $storage->temporaryUrl((string) $user->avatar_path)
                : null,
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
            'date_of_birth' => 'nullable|date',
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
                'date_of_birth' => $data['date_of_birth'] ?? null,
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

    public function storePicture(
        Request $request,
        AuthorizeService $authorize,
        AuditLogWriter $audit,
        ObjectStorageService $storage,
    ): RedirectResponse {
        $authorize->authorize($request->user(), 'profile.edit_own');

        $request->validate([
            'picture' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('picture');
        $user = $request->user();

        $path = $storage->signedUploadPath(
            'uploads',
            (string) $user->id,
            $file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg'
        );

        $audit->withAudit($user, 'profile.update', function () use ($storage, $path, $file, $user) {
            $storage->store($path, $file->get() ?: '');
            $user->update(['avatar_path' => $path]);

            return $user->fresh();
        }, 'User');

        return back()->with('status', __('learning.profile_picture_saved'));
    }
}
