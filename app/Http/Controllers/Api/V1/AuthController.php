<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request, AuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:120',
        ]);

        $result = $auth->issueApiToken($data['email'], $data['password'], $data['device_name'] ?? null);

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'user' => (new UserResource($result['user']))->resolve($request),
            ],
        ]);
    }

    public function logout(Request $request, AuthService $auth): JsonResponse
    {
        $auth->revokeApiToken($request->user());

        return response()->json(status: 204);
    }
}
