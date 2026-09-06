<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Services\Live\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function mine(Request $request, AttendanceService $attendance): JsonResponse
    {
        $entries = $attendance->historyForStudent($request->user());
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $slice = $entries->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $slice->map(fn ($entry) => [
                'id' => $entry->id,
                'session_id' => $entry->class_session_id,
                'offering_id' => $entry->session?->offering_id,
                'title' => $entry->session?->title,
                'scheduled_start' => $entry->session?->scheduled_start?->toIso8601String(),
                'status' => $entry->status->value,
                'minutes_attended' => $entry->minutes_attended,
                'source' => $entry->source->value,
            ]),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $entries->count(),
            ],
        ]);
    }

    public function offeringMine(Request $request, CourseOffering $offering, AttendanceService $attendance): JsonResponse
    {
        $student = $request->user();
        $attendance->historyForStudent($student, $offering);
        $policy = $attendance->resolvePolicy($offering);

        return response()->json([
            'data' => [
                'percent' => $attendance->percentFor($student, $offering),
                'threshold' => $policy?->min_percentage,
                'policy_enabled' => $policy?->is_enabled,
                'meets_threshold' => $policy === null
                    ? null
                    : (($attendance->percentFor($student, $offering) ?? 0) >= $policy->min_percentage),
            ],
        ]);
    }

    public function checkIn(Request $request, ClassSession $session, AttendanceService $attendance): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $entry = $attendance->selfCheckIn($request->user(), $session, $data['code']);

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'status' => $entry->status->value,
                'session_id' => $entry->class_session_id,
            ],
        ], 201);
    }
}
