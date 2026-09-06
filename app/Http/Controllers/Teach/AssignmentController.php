<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\CourseOffering;
use App\Models\User;
use App\Services\Assessment\AssignmentService;
use App\Services\Teach\TeachAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function __construct(
        private readonly TeachAccessService $teachAccess,
        private readonly AssignmentService $assignments,
    ) {}

    public function index(Request $request, CourseOffering $offering): View
    {
        $this->guardTeach($request, $offering);

        return view('teach.assignments.index', [
            'offering' => $offering->load('course'),
            'stats' => $this->assignments->dashboardStats($request->user(), $offering),
        ]);
    }

    public function remind(Request $request, CourseOffering $offering, Assignment $assignment): RedirectResponse
    {
        $this->guardTeach($request, $offering);

        $count = $this->assignments->remindUnsubmitted($request->user(), $assignment);

        return back()->with('status', __('assessment.reminder_sent', ['count' => $count]));
    }

    public function markReceived(Request $request, CourseOffering $offering, Assignment $assignment): RedirectResponse
    {
        $this->guardTeach($request, $offering);

        $data = $request->validate([
            'student_id' => 'required|string|exists:users,id',
        ]);

        $this->assignments->markReceivedForStudent(
            $request->user(),
            $assignment,
            User::query()->findOrFail($data['student_id']),
        );

        return back()->with('status', __('assessment.marked_received'));
    }

    public function bulkGradeOffline(Request $request, CourseOffering $offering, Assignment $assignment): RedirectResponse
    {
        $this->guardTeach($request, $offering);

        $data = $request->validate([
            'grades' => 'required|string',
        ]);

        $gradesByStudentId = [];
        foreach (preg_split('/\r?\n/', trim($data['grades'])) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$studentId, $rawScore, $feedback] = array_pad(explode(',', $line, 3), 3, null);
            if ($studentId === null || $rawScore === null) {
                continue;
            }

            $gradesByStudentId[trim($studentId)] = [
                'raw_score' => (float) trim($rawScore),
                'feedback' => $feedback !== null ? trim($feedback) : null,
            ];
        }

        $this->assignments->bulkGradeOffline($request->user(), $assignment, $gradesByStudentId);

        return back()->with('status', __('assessment.bulk_graded'));
    }

    private function guardTeach(Request $request, CourseOffering $offering): void
    {
        $user = $request->user();
        abort_unless($this->teachAccess->canTeach($user), 403);
        $this->teachAccess->assertCanTeachOffering($user, $offering);
    }
}
