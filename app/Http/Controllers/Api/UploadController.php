<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UploadController extends Controller
{
    public function store(
        Request $request,
        ObjectStorageService $storage,
        AuthorizeService $authorize,
    ): JsonResponse {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'prefix' => ['nullable', 'string', Rule::in(['uploads', 'help-media'])],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $data['file'];
        $user = $request->user();
        $prefix = $data['prefix'] ?? 'uploads';

        if ($prefix === 'help-media') {
            $authorize->authorize($user, 'help.manage');
        }

        $path = $storage->signedUploadPath(
            $prefix,
            (string) $user->id,
            $file->getClientOriginalExtension() ?: $file->extension()
        );

        $storage->store($path, $file->get() ?: '');

        return response()->json([
            'path' => $path,
            'url' => $storage->temporaryUrl($path, 60),
        ], 201);
    }
}
