<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $request, ObjectStorageService $storage): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $data['file'];
        $user = $request->user();

        $path = $storage->signedUploadPath(
            'uploads',
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
