<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\Live\AttendanceService;
use App\Services\Live\LiveSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveSessionAdminController extends Controller
{
    public function index(CourseOffering $offering): View
    {
        return view('admin.live.index', [
            'offering' => $offering->load('course'),
            'sessions' => LiveSession::query()
                ->where('offering_id', $offering->id)
                ->orderBy('scheduled_start')
                ->with('attendance.student')
                ->get(),
        ]);
    }

    public function store(Request $request, CourseOffering $offering, LiveSessionService $live): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'scheduled_start' => 'required|date',
            'duration_minutes' => 'required|integer|min:15|max:480',
        ]);

        $live->schedule($request->user(), $offering, $data);

        return back()->with('status', __('live.scheduled'));
    }

    public function importAttendance(Request $request, LiveSession $session, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate([
            'participants' => 'required|array|min:1',
            'participants.*.email' => 'nullable|email',
            'participants.*.user_id' => 'nullable|string',
            'participants.*.minutes' => 'required|integer|min:0',
        ]);

        $count = $attendance->importFromZoom($request->user(), $session, $data['participants']);

        return back()->with('status', __('live.attendance_imported', ['count' => $count]));
    }

    public function overrideAttendance(Request $request, LiveSession $session, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'status' => 'required|in:PRESENT,ABSENT',
            'minutes' => 'nullable|integer|min:0',
        ]);

        $attendance->override(
            $request->user(),
            $session,
            User::query()->findOrFail($data['student_id']),
            AttendanceStatus::from($data['status']),
            $data['minutes'] ?? null
        );

        return back()->with('status', __('live.attendance_overridden'));
    }
}
