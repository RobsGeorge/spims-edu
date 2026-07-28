<?php

namespace App\Http\Controllers;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\StudentProgram;
use App\Services\Enrollment\DegreeAuditService;
use App\Services\Enrollment\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(Request $request, DegreeAuditService $audit): View
    {
        $user = $request->user();

        return view('enrollments.index', [
            'enrollments' => Enrollment::query()
                ->where('student_id', $user->id)
                ->with(['offering.course', 'offering.semester'])
                ->latest('enrolled_at')
                ->get(),
            'programs' => $audit->activePrograms($user),
            'offerings' => CourseOffering::query()
                ->with(['course', 'semester'])
                ->whereIn('status', ['OPEN', 'IN_PROGRESS', 'DRAFT'])
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request, EnrollmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'offering_id' => 'required|exists:course_offerings,id',
            'student_program_id' => 'nullable|exists:student_programs,id',
        ]);

        $offering = CourseOffering::query()->findOrFail($data['offering_id']);
        $service->register(
            $request->user(),
            $offering,
            $data['student_program_id'] ?? null
        );

        return back()->with('status', __('enrollment.registered'));
    }

    public function drop(Request $request, Enrollment $enrollment, EnrollmentService $service): RedirectResponse
    {
        $service->drop($request->user(), $enrollment);

        return back()->with('status', __('enrollment.dropped'));
    }

    public function withdraw(Request $request, Enrollment $enrollment, EnrollmentService $service): RedirectResponse
    {
        $service->withdraw($request->user(), $enrollment);

        return back()->with('status', __('enrollment.withdrawn'));
    }

    public function audit(Request $request, StudentProgram $studentProgram, DegreeAuditService $audit): View
    {
        abort_unless($studentProgram->student_id === $request->user()->id || $request->user()->isSuperAdmin(), 403);

        return view('enrollments.audit', [
            'audit' => $audit->audit($request->user(), $studentProgram),
            'studentProgram' => $studentProgram->load('program'),
        ]);
    }
}
