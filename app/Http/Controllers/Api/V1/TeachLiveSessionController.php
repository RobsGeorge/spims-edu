<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\LiveSession;
use App\Services\Live\AttendanceService;
use App\Support\Api\ConditionalGet;
use App\Support\Api\IdempotencyStore;
use App\Support\AuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachLiveSessionController extends Controller
{
    public function index(Request $request, CourseOffering $offering, AuthorizeService $authorize, ConditionalGet $conditional): JsonResponse
    {
        $authorize->authorize($request->user(), 'live.schedule', $offering);

        $sessions = LiveSession::query()
            ->where('offering_id', $offering->id)
            ->orderBy('scheduled_start')
            ->get();

        return $conditional->json($request, [
            'data' => $sessions->map(fn (LiveSession $session) => [
                'id' => $session->id,
                'offering_id' => $session->offering_id,
                'title' => $session->title,
                'scheduled_start' => $session->scheduled_start?->toIso8601String(),
                'duration_minutes' => $session->duration_minutes,
                'join_url' => $session->zoom_join_url,
                'recording_url' => $session->recording_url,
            ])->values()->all(),
        ]);
    }

    public function importAttendance(
        Request $request,
        LiveSession $liveSession,
        AttendanceService $attendance,
        IdempotencyStore $idempotency,
    ): JsonResponse {
        $data = $request->validate([
            'participants' => 'required|array|min:1',
            'participants.*.email' => 'nullable|email',
            'participants.*.user_id' => 'nullable|string',
            'participants.*.minutes' => 'required|integer|min:0',
        ]);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.live.import:'.$liveSession->id,
            $request->header('Idempotency-Key'),
            fn () => ['imported' => $attendance->importFromZoom($request->user(), $liveSession, $data['participants'])],
        );

        return response()->json(['data' => $payload]);
    }
}
