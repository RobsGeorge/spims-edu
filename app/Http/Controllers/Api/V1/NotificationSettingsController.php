<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Communications\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'notify_email' => (bool) ($user->notify_email ?? true),
                'preferences' => $this->preferences->matrix($user, $user),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.event_key' => ['required', 'string', 'max:80'],
            'preferences.*.channel' => ['required', 'in:in_app,mail,whatsapp'],
            'preferences.*.enabled' => ['required', 'boolean'],
        ]);

        $matrix = $this->preferences->put($request->user(), $request->user(), $data['preferences']);

        return response()->json([
            'data' => [
                'notify_email' => (bool) ($request->user()->notify_email ?? true),
                'preferences' => $matrix,
            ],
        ]);
    }
}
