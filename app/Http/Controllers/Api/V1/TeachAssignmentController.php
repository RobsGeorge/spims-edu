<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CourseOffering;
use App\Services\Assessment\AssignmentService;
use App\Support\Api\StudentPayload;
use App\Support\AuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachAssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentService $assignments,
        private readonly AuthorizeService $authorize,
    ) {}

    public function index(Request $request, CourseOffering $offering): JsonResponse
    {
        return response()->json([
            'data' => $this->assignments->dashboardStats($request->user(), $offering),
        ]);
    }

    public function submissions(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorize->authorize($request->user(), 'assignments.grade', $assignment);

        $rows = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->with('student')
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (AssignmentSubmission $submission) => $this->submissionPayload($submission))
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function grade(Request $request, AssignmentSubmission $assignmentSubmission): JsonResponse
    {
        $data = $request->validate([
            'raw_score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ]);

        $graded = $this->assignments->grade(
            $request->user(),
            $assignmentSubmission,
            (float) $data['raw_score'],
            $data['feedback'] ?? null,
        );

        return response()->json(['data' => $this->submissionPayload($graded)]);
    }

    public function markReceived(Request $request, AssignmentSubmission $assignmentSubmission): JsonResponse
    {
        $updated = $this->assignments->markReceived($request->user(), $assignmentSubmission);

        return response()->json(['data' => $this->submissionPayload($updated)]);
    }

    public function remindUnsubmitted(Request $request, Assignment $assignment): JsonResponse
    {
        $count = $this->assignments->remindUnsubmitted($request->user(), $assignment);

        return response()->json(['data' => ['reminded' => $count]]);
    }

    /** @return array<string, mixed> */
    private function submissionPayload(AssignmentSubmission $submission): array
    {
        $submission->loadMissing('student');

        return [
            'id' => $submission->id,
            'assignment_id' => $submission->assignment_id,
            'student_id' => $submission->student_id,
            'first_name' => $submission->student?->first_name,
            'last_name' => $submission->student?->last_name,
            'attempt_no' => $submission->attempt_no,
            'text_body' => $submission->text_body,
            'submitted_at' => StudentPayload::iso($submission->submitted_at),
            'is_late' => $submission->is_late,
            'raw_score' => $submission->raw_score,
            'final_score' => $submission->final_score,
            'feedback' => $submission->feedback,
            'graded_at' => StudentPayload::iso($submission->graded_at),
            'received_at' => StudentPayload::iso($submission->received_at),
        ];
    }
}
