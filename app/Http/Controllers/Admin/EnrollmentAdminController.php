<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Enrollment\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnrollmentAdminController extends Controller
{
    public function overrideRegister(Request $request, EnrollmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'offering_id' => 'required|exists:course_offerings,id',
            'student_program_id' => 'nullable|exists:student_programs,id',
        ]);

        $student = User::query()->findOrFail($data['student_id']);
        $offering = CourseOffering::query()->findOrFail($data['offering_id']);

        $service->register(
            $student,
            $offering,
            $data['student_program_id'] ?? null,
            adminOverride: true,
            actor: $request->user()
        );

        return back()->with('status', __('enrollment.override_done'));
    }

    public function financialHold(Request $request, User $user, EnrollmentService $service): RedirectResponse
    {
        $data = $request->validate(['held' => 'required|boolean']);
        $service->setFinancialHold($request->user(), $user, $request->boolean('held'));

        return back()->with('status', __('enrollment.hold_updated'));
    }

    public function waitlist(CourseOffering $offering): View
    {
        return view('admin.enrollments.waitlist', [
            'offering' => $offering->load('course'),
            'waitlisted' => Enrollment::query()
                ->where('offering_id', $offering->id)
                ->where('status', \App\Enums\EnrollmentStatus::Waitlisted)
                ->with('student')
                ->orderBy('enrolled_at')
                ->get(),
        ]);
    }
}
