<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'preferredLocale' => $user->preferred_locale,
            'themePreference' => $user->theme_preference?->value,
            'roles' => $user->roleTypes()->pluck('value'),
        ]);
    }
}
