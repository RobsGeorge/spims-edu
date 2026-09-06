<?php

namespace App\Services\Completion;

use App\Enums\CompletionCriterionKind;
use App\Enums\CompletionOutcome;
use App\Enums\EnrollmentStatus;
use App\Exceptions\AuthorizationException;
use App\Models\CompletionCriterion;
use App\Models\CompletionResult;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\DiscussionBoard;
use App\Models\DiscussionGrade;
use App\Models\DiscussionThread;
use App\Models\Enrollment;
use App\Models\EnrollmentItemCompletion;
use App\Models\OfferingClosing;
use App\Models\User;
use App\Services\Gradebook\GradebookService;
use App\Services\Live\AttendanceService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;

/**
 * Evaluates S4 completion criteria (G-13) for an offering's cohort and writes
 * `completion_results`.
 *
 * Conjunction rule: every `is_required = true` criterion must independently
 * pass for the outcome to be COMPLETED. This is deliberately NOT a weighted
 * sum — a student who clears a 90% MIN_GRADE bar but fails a required 75%
 * MIN_ATTENDANCE bar is NOT_COMPLETED, full stop. `met_criteria` records each
 * criterion's individual pass/fail so staff can see exactly which one failed.
 */
class CompletionService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly GradebookService $gradebook,
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * Evaluates every applicable criterion (course-level criteria apply to
     * every offering of that course; offering-level criteria apply only to
     * this offering — both sets are merged) against every enrolled or
     * already-completed student, and upserts one `completion_results` row per
     * student — idempotent: re-running with unchanged underlying data produces
     * the same `outcome`/`met_criteria` (only `evaluated_at` moves).
     *
     * Audited once per call (one `completion.evaluate` row referencing the
     * offering), not once per student — a 40-student cohort re-evaluation
     * should not flood the audit log with 40 near-identical rows when the
     * action taken was a single "evaluate this offering" command.
     *
     * @return Collection<int, CompletionResult>
     */
    public function evaluate(User $actor, CourseOffering $offering): Collection
    {
        $this->authorize->authorize($actor, 'completion.configure', $offering);

        $criteria = $this->criteriaFor($offering);
        $grace = OfferingClosing::query()->where('offering_id', $offering->id)->first()?->grace_marks ?? [];

        $enrollments = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->with('student')
            ->get();

        $results = collect();

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            if ($student === null) {
                continue;
            }

            $graceForStudent = (float) ($grace[$student->id] ?? 0);
            [$met, $outcome] = $this->evaluateStudent($enrollment, $student, $offering, $criteria, $graceForStudent);

            $results->push(CompletionResult::query()->updateOrCreate(
                ['offering_id' => $offering->id, 'student_id' => $student->id],
                ['met_criteria' => $met, 'outcome' => $outcome->value, 'evaluated_at' => now()]
            ));
        }

        $this->audit->write($actor, 'completion.evaluate', 'CourseOffering', $offering->id, after: [
            'student_count' => $results->count(),
            'criteria_count' => $criteria->count(),
        ]);

        return $results;
    }

    /**
     * The authenticated student's own completion result for one offering.
     * `completion.view` grants STUDENT an unconditional 'O' (STUDENT is not a
     * scoped role — see config/permission_scopes.php), so "own record only" is
     * enforced here, the same way AttendanceService::historyForStudent() scopes
     * itself by student id rather than relying on AuthorizeService's resource
     * scoping. A student probing an offering they are not enrolled in gets 403,
     * not a silently-empty result.
     */
    public function own(User $student, CourseOffering $offering): ?CompletionResult
    {
        $this->authorize->authorize($student, 'completion.view');

        $enrolled = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('offering_id', $offering->id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->exists();

        if (! $enrolled) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        return CompletionResult::query()
            ->where('offering_id', $offering->id)
            ->where('student_id', $student->id)
            ->first();
    }

    /**
     * The full cohort view for staff. `completion.view` IS in offering_scoped,
     * so an INSTRUCTOR/TA grant only reaches offerings they are staffed on —
     * ACADEMIC_ADMIN holds an unscoped 'F' and sees every offering.
     *
     * @return Collection<int, CompletionResult>
     */
    public function cohort(User $actor, CourseOffering $offering): Collection
    {
        $this->authorize->authorize($actor, 'completion.view', $offering);

        return CompletionResult::query()
            ->where('offering_id', $offering->id)
            ->with('student')
            ->get();
    }

    /**
     * All criteria touching a course: its own course-level rows plus every
     * offering-level row for any offering of that course — the full picture an
     * admin needs to see on the per-course criteria editor.
     *
     * @return Collection<int, CompletionCriterion>
     */
    public function criteriaForCourse(User $actor, Course $course): Collection
    {
        $this->authorize->authorize($actor, 'completion.configure', $course);

        $offeringIds = CourseOffering::query()->where('course_id', $course->id)->pluck('id');

        return CompletionCriterion::query()
            ->where('course_id', $course->id)
            ->orWhereIn('offering_id', $offeringIds)
            ->with('offering')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Exactly one of course-scoped or offering-scoped: pass `offering_id` in
     * `$data` to scope the criterion to one offering, omit it to apply to
     * every offering of the course. Enforced here at the service level, not a
     * DB constraint — the same approach EmailTemplate's identically-shaped
     * nullable-scope columns already take in this codebase.
     *
     * @param  array{kind: string, threshold?: ?float, content_item_id?: ?string, is_required?: bool, offering_id?: ?string}  $data
     */
    public function addCriterion(User $actor, Course $course, array $data): CompletionCriterion
    {
        $offering = ! empty($data['offering_id'])
            ? CourseOffering::query()->where('course_id', $course->id)->findOrFail($data['offering_id'])
            : null;

        $this->authorize->authorize($actor, 'completion.configure', $offering ?? $course);

        return $this->audit->withAudit($actor, 'completion.criteria_create', function () use ($course, $offering, $data) {
            return CompletionCriterion::query()->create([
                'course_id' => $offering === null ? $course->id : null,
                'offering_id' => $offering?->id,
                'kind' => $data['kind'],
                'threshold' => $data['threshold'] ?? null,
                'content_item_id' => $data['content_item_id'] ?? null,
                'is_required' => array_key_exists('is_required', $data)
                    ? filter_var($data['is_required'], FILTER_VALIDATE_BOOLEAN)
                    : true,
            ]);
        }, CompletionCriterion::class);
    }

    public function deleteCriterion(User $actor, CompletionCriterion $criterion): void
    {
        $resource = $criterion->isOfferingScoped() ? $criterion->offering : $criterion->course;
        $this->authorize->authorize($actor, 'completion.configure', $resource);

        $this->audit->withAudit($actor, 'completion.criteria_delete', function () use ($criterion) {
            $criterion->delete();

            return $criterion;
        }, CompletionCriterion::class);
    }

    /**
     * @return Collection<int, CompletionCriterion>
     */
    private function criteriaFor(CourseOffering $offering): Collection
    {
        return CompletionCriterion::query()
            ->where(function ($q) use ($offering) {
                $q->where('offering_id', $offering->id)
                    ->orWhere('course_id', $offering->course_id);
            })
            ->get();
    }

    /**
     * @param  Collection<int, CompletionCriterion>  $criteria
     * @return array{0: array<string, array{kind: string, is_required: bool, passed: bool}>, 1: CompletionOutcome}
     */
    private function evaluateStudent(
        Enrollment $enrollment,
        User $student,
        CourseOffering $offering,
        Collection $criteria,
        float $grace,
    ): array {
        $met = [];
        $anyRequired = false;
        $requiredFailed = false;

        foreach ($criteria as $criterion) {
            $passed = $this->criterionPassed($criterion, $enrollment, $student, $offering, $grace);

            $met[$criterion->id] = [
                'kind' => $criterion->kind->value,
                'is_required' => $criterion->is_required,
                'passed' => $passed,
            ];

            if ($criterion->is_required) {
                $anyRequired = true;
                if (! $passed) {
                    $requiredFailed = true;
                }
            }
        }

        // No criteria at all, or criteria exist but none are required: there is
        // no required bar to clear, so completion is undetermined rather than an
        // automatic pass — PENDING, not COMPLETED.
        $outcome = match (true) {
            ! $anyRequired => CompletionOutcome::Pending,
            $requiredFailed => CompletionOutcome::NotCompleted,
            default => CompletionOutcome::Completed,
        };

        return [$met, $outcome];
    }

    private function criterionPassed(
        CompletionCriterion $criterion,
        Enrollment $enrollment,
        User $student,
        CourseOffering $offering,
        float $grace,
    ): bool {
        return match ($criterion->kind) {
            CompletionCriterionKind::MinGrade => $this->passesMinGrade($criterion, $enrollment, $grace),
            CompletionCriterionKind::MinAttendance => $this->passesMinAttendance($criterion, $student, $offering),
            CompletionCriterionKind::RequiredItem => $this->passesRequiredItem($criterion, $enrollment),
            CompletionCriterionKind::MinDiscussion => $this->passesMinDiscussion($criterion, $student, $offering),
        };
    }

    /**
     * Grace marks (see OfferingClosingService::applyGraceMarks()) are added to
     * the computed percent for this check only — they never mutate the locked
     * enrollment grade record itself.
     */
    private function passesMinGrade(CompletionCriterion $criterion, Enrollment $enrollment, float $grace): bool
    {
        if ($criterion->threshold === null) {
            return false;
        }

        $percent = $this->gradebook->computeEnrollment($enrollment)['percent'] + $grace;

        return $percent >= $criterion->threshold;
    }

    private function passesMinAttendance(CompletionCriterion $criterion, User $student, CourseOffering $offering): bool
    {
        if ($criterion->threshold === null) {
            return false;
        }

        $percent = $this->attendance->percentFor($student, $offering);

        return $percent !== null && $percent >= $criterion->threshold;
    }

    private function passesRequiredItem(CompletionCriterion $criterion, Enrollment $enrollment): bool
    {
        if ($criterion->content_item_id === null) {
            return false;
        }

        return EnrollmentItemCompletion::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('content_item_id', $criterion->content_item_id)
            ->exists();
    }

    private function passesMinDiscussion(CompletionCriterion $criterion, User $student, CourseOffering $offering): bool
    {
        if ($criterion->threshold === null) {
            return false;
        }

        $board = DiscussionBoard::query()->where('offering_id', $offering->id)->first();
        if ($board === null) {
            return false;
        }

        $threadIds = DiscussionThread::query()
            ->where('board_id', $board->id)
            ->where('is_graded', true)
            ->pluck('id');

        $scores = DiscussionGrade::query()
            ->whereIn('thread_id', $threadIds)
            ->where('student_id', $student->id)
            ->whereNotNull('final_score')
            ->pluck('final_score');

        if ($scores->isEmpty()) {
            return false;
        }

        return ((float) $scores->avg()) >= $criterion->threshold;
    }
}
