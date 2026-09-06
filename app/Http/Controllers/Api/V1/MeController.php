<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ThemePreference;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function updatePreferences(
        Request $request,
        AuthorizeService $authorize,
        AuditLogWriter $audit,
    ): UserResource {
        $authorize->authorize($request->user(), 'profile.edit_own');

        $request->merge([
            'preferred_locale' => $request->input('preferred_locale', $request->input('locale')),
            'theme_preference' => $request->input('theme_preference', $request->input('theme')),
        ]);

        $data = $request->validate([
            'preferred_locale' => 'required|in:ar,en,fr',
            'theme_preference' => 'required|in:LIGHT,DARK,SYSTEM',
            'notify_email' => 'nullable|boolean',
        ]);

        $user = $request->user();

        $audit->withAudit($user, 'profile.update', function () use ($user, $data) {
            $user->update([
                'preferred_locale' => $data['preferred_locale'],
                'theme_preference' => ThemePreference::from($data['theme_preference']),
                'notify_email' => array_key_exists('notify_email', $data)
                    ? (bool) $data['notify_email']
                    : $user->notify_email,
            ]);

            return $user->fresh();
        }, 'User');

        return new UserResource($user->fresh());
    }

    public function storePicture(
        Request $request,
        AuthorizeService $authorize,
        AuditLogWriter $audit,
        ObjectStorageService $storage,
    ): UserResource {
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

        return new UserResource($user->fresh());
    }
}
