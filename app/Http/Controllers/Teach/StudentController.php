<?php

namespace App\Http\Controllers\Teach;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teach\Concerns\GuardsTeachOffering;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Gradebook\GradebookService;
use App\Services\Live\AttendanceService;
use App\Support\AuthorizeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    use GuardsTeachOffering;

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly GradebookService $gradebook,
        private readonly AttendanceService $attendance,
    ) {}

    public function show(Request $request, CourseOffering $offering, User $student): View
    {
        $this->guardTeach($request, $offering);
        $this->authorize->authorize($request->user(), 'roster.view', $offering);
        $this->authorize->authorize($request->user(), 'offerings.view', $offering);

        $enrollment = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->where('student_id', $student->id)
            ->first();

        abort_unless($enrollment !== null, 404);

        $offering->loadMissing(['course', 'semester']);

        $grades = in_array($enrollment->status, [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed], true)
            ? $this->gradebook->computeEnrollment($enrollment)
            : null;

        return view('teach.students.show', [
            'offering' => $offering,
            'student' => $student,
            'enrollment' => $enrollment,
            'grades' => $grades,
            'attendancePercent' => $this->attendance->percentFor($student, $offering),
        ]);
    }
}
