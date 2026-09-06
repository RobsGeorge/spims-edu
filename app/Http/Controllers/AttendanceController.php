<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Services\Live\AttendanceService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, AttendanceService $attendance, AuthorizeService $authorize): View
    {
        $user = $request->user();
        $authorize->authorize($user, 'attendance.view_own');

        $entries = $attendance->historyForStudent($user);
        $offeringIds = Enrollment::query()
            ->where('student_id', $user->id)
            ->pluck('offering_id');

        $percents = [];
        foreach ($offeringIds as $offeringId) {
            $offering = \App\Models\CourseOffering::query()->find($offeringId);
            if ($offering === null) {
                continue;
            }
            $percents[$offeringId] = [
                'percent' => $attendance->percentFor($user, $offering),
                'policy' => $attendance->resolvePolicy($offering),
                'offering' => $offering->load('course'),
            ];
        }

        return view('attendance.history', [
            'entries' => $entries,
            'percents' => $percents,
        ]);
    }

    public function checkInForm(): View
    {
        return view('attendance.check-in');
    }

    public function checkIn(Request $request, AttendanceService $attendance, AuthorizeService $authorize): RedirectResponse
    {
        $authorize->authorize($request->user(), 'attendance.self_check_in');

        $data = $request->validate([
            'code' => 'required|string|max:32',
            'session_id' => 'nullable|string|exists:class_sessions,id',
        ]);

        $session = isset($data['session_id'])
            ? ClassSession::query()->findOrFail($data['session_id'])
            : ClassSession::query()
                ->whereHas('checkInCodes', fn ($q) => $q->where('code', $data['code']))
                ->first();

        if ($session === null) {
            throw ValidationException::withMessages([
                'code' => [__('attendance.check_in_invalid')],
            ]);
        }

        $attendance->selfCheckIn($request->user(), $session, $data['code']);

        return redirect()
            ->route('attendance.index')
            ->with('status', __('attendance.check_in_success'));
    }
}
