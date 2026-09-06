<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendancePolicy;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Services\Live\AttendanceService;
use App\Support\AuthorizeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceAdminController extends Controller
{
    public function policy(Request $request, AuthorizeService $authorize, AttendanceService $attendance): View
    {
        $authorize->authorize($request->user(), 'attendance.configure');

        $offeringId = $request->query('offering_id');
        $offering = $offeringId ? CourseOffering::query()->with('course')->find($offeringId) : null;
        $policy = $offering
            ? $attendance->resolvePolicy($offering)
            : AttendancePolicy::query()->whereNull('offering_id')->first();

        return view('admin.attendance.policy', [
            'policy' => $policy,
            'offering' => $offering,
            'offerings' => CourseOffering::query()->with('course')->orderByDesc('created_at')->limit(50)->get(),
        ]);
    }

    public function savePolicy(Request $request, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate([
            'offering_id' => 'nullable|string|exists:course_offerings,id',
            'min_percentage' => 'required|integer|min:0|max:100',
            'late_grade_percentage' => 'required|integer|min:0|max:100',
            'counts_toward_grade' => 'nullable|boolean',
            'is_enabled' => 'nullable|boolean',
        ]);

        $attendance->savePolicy($request->user(), [
            ...$data,
            'counts_toward_grade' => $request->boolean('counts_toward_grade'),
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        return back()->with('status', __('attendance.policy_saved'));
    }

    public function report(Request $request, AuthorizeService $authorize, AttendanceService $attendance): View
    {
        $authorize->authorize($request->user(), 'attendance.report');

        $offerings = CourseOffering::query()
            ->with('course')
            ->whereIn('id', ClassSession::query()->select('offering_id'))
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        $rows = [];
        foreach ($offerings as $offering) {
            $rows[] = [
                'offering' => $offering,
                'report' => $attendance->report($request->user(), $offering),
            ];
        }

        return view('admin.attendance.report', [
            'rows' => $rows,
        ]);
    }
}
