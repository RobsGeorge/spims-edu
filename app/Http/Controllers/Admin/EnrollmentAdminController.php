<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Enrollment\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnrollmentAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.enrollments.index', [
            'students' => User::query()
                ->whereHas('roles', fn ($query) => $query->where('role', RoleType::Student))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'offerings' => CourseOffering::query()
                ->with(['course', 'semester'])
                ->latest()
                ->get(),
            'programs' => StudentProgram::query()
                ->with(['student', 'program'])
                ->where('status', StudentProgramStatus::Active)
                ->get(),
            'holds' => Setting::query()->find('enrollment.financial_holds')?->value['user_ids'] ?? [],
        ]);
    }

    public function overrideRegister(Request $request, EnrollmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => 'required|exists:users,id',
            'offering_id' => 'required|exists:course_offerings,id',
            'student_program_id' => 'nullable|exists:student_programs,id',
            'is_audit' => 'sometimes|boolean',
        ]);

        $student = User::query()->findOrFail($data['student_id']);
        $offering = CourseOffering::query()->findOrFail($data['offering_id']);

        $service->register(
            $student,
            $offering,
            $data['student_program_id'] ?? null,
            adminOverride: true,
            actor: $request->user(),
            isAudit: $request->boolean('is_audit')
        );

        return back()->with('status', __('enrollment.override_done'));
    }

    public function financialHold(Request $request, User $user, EnrollmentService $service): RedirectResponse
    {
        $request->validate(['held' => 'required|boolean']);
        $service->setFinancialHold($request->user(), $user, $request->boolean('held'));

        return back()->with('status', __('enrollment.hold_updated'));
    }

    public function waitlist(CourseOffering $offering): View
    {
        return view('admin.enrollments.waitlist', [
            'offering' => $offering->load('course'),
            'waitlisted' => Enrollment::query()
                ->where('offering_id', $offering->id)
                ->where('status', EnrollmentStatus::Waitlisted)
                ->with('student')
                ->orderBy('enrolled_at')
                ->get(),
        ]);
    }
}
