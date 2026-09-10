<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingStatus;
use App\Enums\OfferingStaffRole;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCourseController extends Controller
{
    /**
     * Public course detail page — guest-accessible.
     * Route: GET /courses/{code}
     */
    public function show(Request $request, string $code): View
    {
        $course = Course::query()
            ->where('code', $code)
            ->where('active', true)
            ->with([
                // Prerequisites (linked courses)
                'prerequisites' => fn ($q) => $q->where('active', true),

                // Programs that include this course
                'programCourses.program' => fn ($q) => $q->where('active', true),

                // Open offerings with instructors and staff
                'offerings' => fn ($q) => $q
                    ->where('status', OfferingStatus::Open)
                    ->with([
                        'staff' => fn ($s) => $s
                            ->where('role', OfferingStaffRole::Instructor)
                            ->with('user'),
                        'semester',
                    ]),
            ])
            ->firstOrFail();

        // Build per-offering enrollment status map for authenticated students
        $enrollmentMap = [];
        $user = $request->user();
        if ($user !== null) {
            $offeringIds = $course->offerings->pluck('id')->all();
            if (!empty($offeringIds)) {
                $enrollmentMap = Enrollment::query()
                    ->where('student_id', $user->id)
                    ->whereIn('offering_id', $offeringIds)
                    ->whereIn('status', [
                        EnrollmentStatus::Enrolled,
                        EnrollmentStatus::Waitlisted,
                    ])
                    ->get()
                    ->keyBy('offering_id')
                    ->toArray();
            }
        }

        return view('courses.show', [
            'course'        => $course,
            'enrollmentMap' => $enrollmentMap,
            'user'          => $user,
        ]);
    }
}
