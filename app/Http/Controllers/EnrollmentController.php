<?php

namespace App\Http\Controllers;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\ProgramCourse;
use App\Models\StudentProgram;
use App\Services\Enrollment\AdvisingService;
use App\Services\Enrollment\DegreeAuditService;
use App\Services\Enrollment\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(Request $request, DegreeAuditService $audit, EnrollmentService $enrollments): View
    {
        $user = $request->user();

        $offerings = $enrollments->registerableOfferings($user)->map(function (CourseOffering $offering) {
            $offering->setAttribute('mode_label', __('offering_mode.'.$offering->mode->value));

            return $offering;
        });

        return view('enrollments.index', [
            'enrollments' => Enrollment::query()
                ->where('student_id', $user->id)
                ->with(['offering.course', 'offering.semester'])
                ->latest('enrolled_at')
                ->get(),
            'programs' => $audit->activePrograms($user),
            'offerings' => $offerings,
        ]);
    }

    public function store(Request $request, EnrollmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'offering_id' => 'required|exists:course_offerings,id',
            'student_program_id' => 'nullable|exists:student_programs,id',
        ]);

        $offering = CourseOffering::query()->findOrFail($data['offering_id']);
        $conflict = $service->hasLiveSessionConflict($request->user(), $offering);

        $service->register(
            $request->user(),
            $offering,
            $data['student_program_id'] ?? null
        );

        $redirect = back()->with('status', __('enrollment.registered'));

        if ($conflict) {
            $redirect->with('warning', __('enrollment.schedule_conflict_warning'));
        }

        return $redirect;
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

    public function audit(Request $request, StudentProgram $studentProgram, DegreeAuditService $audit, AdvisingService $advising): View
    {
        $studentProgram->load(['program', 'student']);
        $advising->assertCanViewStudentProgram($request->user(), $studentProgram);

        $hypothetical = array_values(array_filter(
            (array) $request->input('hypothetical_course_ids', []),
            fn ($id) => is_string($id) || is_int($id)
        ));
        $hypothetical = array_map(fn ($id) => (string) $id, $hypothetical);

        $baseline = $audit->audit($studentProgram->student, $studentProgram);
        $result = $hypothetical === []
            ? $baseline
            : $audit->whatIf($studentProgram, $hypothetical);

        $metCodes = collect($baseline['met'])->pluck('code')->all();
        $remainingCourses = ProgramCourse::query()
            ->where('program_id', $studentProgram->program_id)
            ->with('course')
            ->get()
            ->filter(fn (ProgramCourse $pc) => ! in_array($pc->course->code, $metCodes, true));

        return view('enrollments.audit', [
            'audit' => $result,
            'studentProgram' => $studentProgram,
            'remainingCourses' => $remainingCourses,
            'selectedHypothetical' => $hypothetical,
        ]);
    }
}
