<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Events\EventCheckInService;
use App\Support\Api\StudentPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventCheckInController extends Controller
{
    public function verify(Request $request, EventCheckInService $checkIns): JsonResponse
    {
        $data = $request->validate([
            'payload' => 'required|string|max:512',
        ]);

        $checkIn = $checkIns->verify($request->user(), $data['payload']);

        return response()->json([
            'data' => [
                'id' => $checkIn->id,
                'reservation_id' => $checkIn->reservation_id,
                'checked_in_at' => StudentPayload::iso($checkIn->checked_in_at),
                'checked_in_by_id' => $checkIn->checked_in_by_id,
            ],
        ], 201);
    }
}
