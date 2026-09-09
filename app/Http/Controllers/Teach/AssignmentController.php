<?php

namespace App\Http\Controllers\Teach;

use App\Enums\EnrollmentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CourseOffering;
use App\Models\Question;
use App\Models\User;
use App\Services\Assessment\AssignmentService;
use App\Services\Assessment\EssayAiGrader;
use App\Services\Teach\TeachAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function __construct(
        private readonly TeachAccessService $teachAccess,
        private readonly AssignmentService $assignments,
        private readonly EssayAiGrader $essayGrader,
    ) {}

    // ─── Existing methods ────────────────────────────────────────────────

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

    // ─── Step 13 — Grading Workbench ─────────────────────────────────────

    /**
     * 13.1 — Submissions roster: all enrolled students + their submission status.
     * Filters: status (all/submitted/graded/not_submitted) + date range.
     */
    public function submissions(Request $request, CourseOffering $offering, Assignment $assignment): View
    {
        $this->guardTeach($request, $offering);

        $statusFilter = $request->query('status', 'all');
        $from         = $request->query('from');
        $to           = $request->query('to');

        // Build roster: enrolled students LEFT JOINed with their submission for this assignment.
        $query = DB::table('enrollments')
            ->join('users', 'enrollments.student_id', '=', 'users.id')
            ->leftJoin('assignment_submissions', function ($join) use ($assignment) {
                $join->on('assignment_submissions.student_id', '=', 'enrollments.student_id')
                     ->where('assignment_submissions.assignment_id', '=', $assignment->id);
            })
            ->where('enrollments.offering_id', $offering->id)
            ->whereIn('enrollments.status', [
                EnrollmentStatus::Enrolled->value,
                EnrollmentStatus::Completed->value,
            ])
            ->select([
                'users.id            as student_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'assignment_submissions.id          as submission_id',
                'assignment_submissions.attempt_no',
                'assignment_submissions.submitted_at',
                'assignment_submissions.final_score',
                'assignment_submissions.raw_score',
                'assignment_submissions.is_late',
            ]);

        // Status filter
        if ($statusFilter === 'submitted') {
            $query->whereNotNull('assignment_submissions.id')
                  ->whereNull('assignment_submissions.final_score');
        } elseif ($statusFilter === 'graded') {
            $query->whereNotNull('assignment_submissions.final_score');
        } elseif ($statusFilter === 'not_submitted') {
            $query->whereNull('assignment_submissions.id');
        }

        // Date range filter (applies only to submitted rows)
        if ($from) {
            $query->where('assignment_submissions.submitted_at', '>=', $from);
        }
        if ($to) {
            $query->where('assignment_submissions.submitted_at', '<=', $to.' 23:59:59');
        }

        $query->orderByRaw("
            CASE
                WHEN assignment_submissions.final_score IS NULL AND assignment_submissions.id IS NOT NULL THEN 0
                WHEN assignment_submissions.id IS NULL THEN 1
                ELSE 2
            END,
            assignment_submissions.submitted_at ASC NULLS LAST,
            users.last_name ASC
        ");

        $roster = $query->paginate(15)->withQueryString();

        // Next ungraded submission (for the header button)
        $nextUngraded = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->whereNull('final_score')
            ->whereNotNull('submitted_at')
            ->oldest('submitted_at')
            ->first();

        return view('teach.assignments.submissions', [
            'offering'     => $offering->load('course'),
            'assignment'   => $assignment,
            'roster'       => $roster,
            'nextUngraded' => $nextUngraded,
            'statusFilter' => $statusFilter,
            'from'         => $from,
            'to'           => $to,
        ]);
    }

    /**
     * 13.1 — Redirect to the next ungraded submission, or back with a flash
     * message if nothing is left to grade.
     */
    public function nextUngraded(Request $request, CourseOffering $offering, Assignment $assignment): RedirectResponse
    {
        $this->guardTeach($request, $offering);

        $next = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->whereNull('final_score')
            ->whereNotNull('submitted_at')
            ->oldest('submitted_at')
            ->first();

        if ($next === null) {
            return redirect()
                ->route('teach.assignments.submissions.index', [$offering, $assignment])
                ->with('nothing_to_grade', true);
        }

        return redirect()->route(
            'teach.assignments.submissions.show',
            [$offering, $assignment, $next],
        );
    }

    /**
     * 13.2 — Per-submission view: content, files, version history, grading panel.
     */
    public function showSubmission(
        Request $request,
        CourseOffering $offering,
        Assignment $assignment,
        AssignmentSubmission $submission,
    ): View {
        $this->guardTeach($request, $offering);

        $submission->loadMissing(['student', 'versions.gradedBy', 'assignment']);

        return view('teach.assignments.submission', [
            'offering'   => $offering->load('course'),
            'assignment' => $assignment,
            'submission' => $submission,
            'versions'   => $submission->versions()->get(),
        ]);
    }

    /**
     * 13.2 — Grade a submission through AssignmentService (audited).
     */
    public function gradeSubmission(
        Request $request,
        CourseOffering $offering,
        Assignment $assignment,
        AssignmentSubmission $submission,
    ): RedirectResponse {
        $this->guardTeach($request, $offering);

        $data = $request->validate([
            'raw_score' => ['required', 'numeric', 'min:0', 'max:'.$assignment->max_points],
            'feedback'  => ['nullable', 'string', 'max:5000'],
        ]);

        $this->assignments->grade(
            $request->user(),
            $submission,
            (float) $data['raw_score'],
            $data['feedback'] ?? null,
        );

        return redirect()
            ->route('teach.assignments.submissions.show', [$offering, $assignment, $submission])
            ->with('status', __('grading.grade_saved'));
    }

    /**
     * 13.3 — AI-powered grade suggestion. NEVER writes to the database.
     * Returns JSON: {score: float, feedback: string} | {error: string}
     */
    public function aiSuggest(
        Request $request,
        CourseOffering $offering,
        Assignment $assignment,
        AssignmentSubmission $submission,
    ): JsonResponse {
        $this->guardTeach($request, $offering);

        $textBody = (string) ($submission->text_body ?? '');
        if ($textBody === '') {
            return response()->json(['error' => __('grading.ai_no_suggestion')]);
        }

        // Build a transient Question stub from the assignment instructions.
        // EssayAiGrader uses question->prompt, ai_key_points, ai_guidance.
        $question               = new Question();
        $question->prompt       = (string) $assignment->instructions;
        $question->ai_key_points = '';
        $question->ai_guidance  = '';

        $result = $this->essayGrader->suggest($question, $textBody, (float) $assignment->max_points);

        if ($result === null) {
            return response()->json(['error' => __('grading.ai_no_suggestion')]);
        }

        // No write occurs — this is suggestion only.
        return response()->json([
            'score'    => $result['score'],
            'feedback' => $result['rationale'],
        ]);
    }

    private function guardTeach(Request $request, CourseOffering $offering): void
    {
        $user = $request->user();
        abort_unless($this->teachAccess->canTeach($user), 403);
        $this->teachAccess->assertCanTeachOffering($user, $offering);
    }
}
