<?php

namespace App\Services\Assessment;

use App\Enums\DeliveryMode;
use App\Enums\EnrollmentStatus;
use App\Enums\SubmissionType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionVersion;
use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\NotificationReminder;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Offerings\LearningProgressService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly LearningProgressService $progress,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, ContentItem $item, array $data): Assignment
    {
        $this->authorize->authorize($actor, 'assignments.manage', $item);

        return $this->audit->withAudit($actor, 'assignments.create', function () use ($item, $data) {
            return Assignment::query()->create([
                'content_item_id' => $item->id,
                'component_id' => $data['component_id'] ?? null,
                'instructions' => $data['instructions'],
                'submission_type' => SubmissionType::from($data['submission_type'] ?? SubmissionType::Both->value),
                'allowed_file_types' => $data['allowed_file_types'] ?? ['pdf'],
                'max_points' => $data['max_points'] ?? 100,
                'item_weight' => $data['item_weight'] ?? null,
                'released' => (bool) ($data['released'] ?? false),
                'due_date' => $data['due_date'] ?? null,
                'late_penalty_override' => $data['late_penalty_override'] ?? null,
                'delivery_mode' => DeliveryMode::from($data['delivery_mode'] ?? DeliveryMode::Online->value),
                'resubmission_deadline' => $data['resubmission_deadline'] ?? null,
            ]);
        }, 'Assignment');
    }

    public function submit(User $student, Assignment $assignment, ?string $textBody = null, ?string $fileUrl = null): AssignmentSubmission
    {
        $this->authorize->authorize($student, 'assignments.submit');

        // A physical hand-in produces neither text nor a file. markReceived() (staff-side)
        // is the only completion signal for an OFFLINE assignment; there is nothing here
        // for the student to digitally submit.
        if ($assignment->delivery_mode === DeliveryMode::Offline) {
            throw ValidationException::withMessages([
                'assignment' => [__('assessment.offline_use_mark_received')],
            ]);
        }

        $item = $assignment->contentItem()->with('week')->first();
        $offeringId = $item?->week?->offering_id;
        if ($offeringId) {
            $enrolled = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('offering_id', $offeringId)
                ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
                ->exists();
            if (! $enrolled) {
                throw ValidationException::withMessages(['assignment' => [__('assessment.not_enrolled')]]);
            }
        }

        $submittedAt = now();
        $isLate = $assignment->due_date && $submittedAt->gt($assignment->due_date);

        $existing = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing !== null && ! $assignment->allow_resubmission) {
            throw ValidationException::withMessages([
                'assignment' => [__('assessment.resubmission_not_allowed')],
            ]);
        }

        // The deadline only ever gates a resubmission. A first-ever submission always
        // gets in, even if the deadline has already passed — there was nothing to
        // resubmit against yet.
        if ($existing !== null && $assignment->resubmission_deadline !== null && $submittedAt->gt($assignment->resubmission_deadline)) {
            throw ValidationException::withMessages([
                'assignment' => [__('assessment.resubmission_deadline_passed')],
            ]);
        }

        $submission = DB::transaction(function () use ($assignment, $student, $existing, $textBody, $fileUrl, $submittedAt, $isLate) {
            if ($existing === null) {
                return AssignmentSubmission::query()->create([
                    'assignment_id' => $assignment->id,
                    'student_id' => $student->id,
                    'text_body' => $textBody,
                    'file_url' => $fileUrl,
                    'submitted_at' => $submittedAt,
                    'is_late' => (bool) $isLate,
                    'attempt_no' => 1,
                ]);
            }

            $this->archive($existing);

            // A resubmission replaces the graded artifact, so the prior grade no longer
            // describes what is stored. It is preserved on the archived version.
            $existing->update([
                'text_body' => $textBody,
                'file_url' => $fileUrl,
                'submitted_at' => $submittedAt,
                'is_late' => (bool) $isLate,
                'attempt_no' => $existing->attempt_no + 1,
                'raw_score' => null,
                'final_score' => null,
                'feedback' => null,
                'graded_by_id' => null,
                'graded_at' => null,
            ]);

            return $existing->fresh();
        });

        $this->audit->write(
            $student,
            $submission->attempt_no > 1 ? 'assignments.resubmit' : 'assignments.submit',
            'AssignmentSubmission',
            $submission->id,
        );

        if ($item) {
            $this->progress->recordLinkedItemComplete($student, $item);
        }

        return $submission;
    }

    /** Snapshot the current state of a submission before it is replaced. */
    private function archive(AssignmentSubmission $submission): AssignmentSubmissionVersion
    {
        return AssignmentSubmissionVersion::query()->create([
            'submission_id' => $submission->id,
            'attempt_no' => $submission->attempt_no,
            'text_body' => $submission->text_body,
            'file_url' => $submission->file_url,
            'submitted_at' => $submission->submitted_at,
            'is_late' => $submission->is_late,
            'raw_score' => $submission->raw_score,
            'final_score' => $submission->final_score,
            'feedback' => $submission->feedback,
            'graded_by_id' => $submission->graded_by_id,
            'graded_at' => $submission->graded_at,
            'archived_at' => now(),
        ]);
    }

    public function grade(User $grader, AssignmentSubmission $submission, float $rawScore, ?string $feedback = null): AssignmentSubmission
    {
        $this->authorize->authorize($grader, 'assignments.grade', $submission);

        $assignment = $submission->assignment;
        $final = $this->applyLatePenalty($assignment, $submission, $rawScore);

        $submission->update([
            'raw_score' => $rawScore,
            'final_score' => $final,
            'feedback' => $feedback,
            'graded_by_id' => $grader->id,
            'graded_at' => now(),
        ]);

        $this->audit->write($grader, 'assignments.grade', 'AssignmentSubmission', $submission->id);

        return $submission->fresh();
    }

    public function applyLatePenalty(Assignment $assignment, AssignmentSubmission $submission, float $rawScore): float
    {
        if (! $submission->is_late || ! $assignment->due_date) {
            return $rawScore;
        }

        if ($assignment->late_penalty_override !== null) {
            $percent = (float) $assignment->late_penalty_override;

            return round(max(0, $rawScore * (1 - ($percent / 100))), 2);
        }

        $schedule = Setting::query()->find('late_penalty.escalating')?->value['value']
            ?? [0, 10, 20, 30];

        $daysLate = (int) max(1, $assignment->due_date->diffInDays($submission->submitted_at));
        $index = min($daysLate, count($schedule) - 1);
        $percent = (float) ($schedule[$index] ?? end($schedule));

        return round(max(0, $rawScore * (1 - ($percent / 100))), 2);
    }

    /**
     * Mark an existing offline submission row as physically received. The completion
     * signal for an OFFLINE assignment: a physical hand-in never goes through submit().
     */
    public function markReceived(User $actor, AssignmentSubmission $submission): AssignmentSubmission
    {
        $this->authorize->authorize($actor, 'assignments.mark_received', $submission);

        $assignment = $submission->assignment;
        if ($assignment->delivery_mode !== DeliveryMode::Offline) {
            throw ValidationException::withMessages([
                'assignment' => [__('assessment.mark_received_offline_only')],
            ]);
        }

        $submission->update([
            'received_at' => now(),
            'received_by_id' => $actor->id,
        ]);

        $this->audit->write($actor, 'assignments.mark_received', 'AssignmentSubmission', $submission->id);

        return $submission->fresh();
    }

    /**
     * A student may never interact with the system for a purely offline hand-in, so
     * there may be no `AssignmentSubmission` row yet when staff mark it received. This
     * creates that row on demand (student never called submit(), and for OFFLINE
     * assignments submit() refuses anyway) before delegating to markReceived().
     */
    public function markReceivedForStudent(User $actor, Assignment $assignment, User $student): AssignmentSubmission
    {
        $this->authorize->authorize($actor, 'assignments.mark_received', $assignment);

        if ($assignment->delivery_mode !== DeliveryMode::Offline) {
            throw ValidationException::withMessages([
                'assignment' => [__('assessment.mark_received_offline_only')],
            ]);
        }

        $submission = AssignmentSubmission::query()->firstOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            ['attempt_no' => 1, 'submitted_at' => now(), 'is_late' => false],
        );

        return $this->markReceived($actor, $submission);
    }

    /**
     * Reminds every enrolled student on the assignment's offering who has no submission
     * row yet. OFFLINE assignments are excluded: there is no digital act to remind a
     * student to perform, and the completion signal (markReceived) is staff-driven, not
     * student-driven.
     *
     * De-duplication reuses `NotificationReminder` (the same subject_type/subject_id/
     * user_id/sent_at shape S2 uses for scheduled reminders) as an immediate-send log: a
     * row with `sent_at` already set means this student has been reminded about this
     * assignment before, and calling this method again in the same window — the life of
     * the assignment, since there is no separate resubmission-window concept for a first
     * submission — will not remind them again.
     */
    public function remindUnsubmitted(User $actor, Assignment $assignment): int
    {
        $this->authorize->authorize($actor, 'assignments.remind', $assignment);

        if ($assignment->delivery_mode === DeliveryMode::Offline) {
            return 0;
        }

        $offeringId = $assignment->contentItem?->week?->offering_id;
        if (! $offeringId) {
            return 0;
        }

        $submittedStudentIds = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->pluck('student_id');

        $studentIds = Enrollment::query()
            ->where('offering_id', $offeringId)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->whereNotIn('student_id', $submittedStudentIds)
            ->pluck('student_id');

        $count = 0;
        foreach (User::query()->whereIn('id', $studentIds)->get() as $student) {
            $alreadyReminded = NotificationReminder::query()
                ->where('user_id', $student->id)
                ->where('subject_type', Assignment::class)
                ->where('subject_id', $assignment->id)
                ->whereNotNull('sent_at')
                ->exists();

            if ($alreadyReminded) {
                continue;
            }

            $this->notifications->notify(
                $student,
                'assignments.reminder',
                __('assessment.reminder_title'),
                __('assessment.reminder_body', ['assignment' => $assignment->instructions]),
                ['assignment_id' => $assignment->id],
            );

            NotificationReminder::query()->create([
                'user_id' => $student->id,
                'subject_type' => Assignment::class,
                'subject_id' => $assignment->id,
                'remind_at' => now(),
                'sent_at' => now(),
            ]);

            $count++;
        }

        $this->audit->write($actor, 'assignments.remind', 'Assignment', $assignment->id);

        return $count;
    }

    /**
     * Per-assignment staff dashboard: submission, ungraded, and overdue counts for every
     * assignment on the offering.
     *
     * `overdue_count` counts enrolled students who have NOT submitted while the due date
     * has passed (the actionable "who still needs to be chased" number), not students
     * who submitted late — a late-but-submitted assignment is no longer something staff
     * need to act on the same way.
     *
     * @return list<array{assignment_id: string, title: string, delivery_mode: string, enrolled_count: int, submitted_count: int, ungraded_count: int, overdue_count: int}>
     */
    public function dashboardStats(User $actor, CourseOffering $offering): array
    {
        $this->authorize->authorize($actor, 'assignments.dashboard', $offering);

        $enrolledCount = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->count();

        $assignments = Assignment::query()
            ->whereHas('contentItem.week', fn ($q) => $q->where('offering_id', $offering->id))
            ->with('contentItem')
            ->get();

        $stats = [];
        foreach ($assignments as $assignment) {
            $submissions = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->get();
            $submittedCount = $submissions->count();
            $ungradedCount = $submissions->whereNull('final_score')->count();

            $isPastDue = $assignment->due_date !== null && now()->gt($assignment->due_date);
            $overdueCount = $isPastDue ? max(0, $enrolledCount - $submittedCount) : 0;

            $stats[] = [
                'assignment_id' => $assignment->id,
                'title' => $assignment->instructions,
                'delivery_mode' => $assignment->delivery_mode->value,
                'enrolled_count' => $enrolledCount,
                'submitted_count' => $submittedCount,
                'ungraded_count' => $ungradedCount,
                'overdue_count' => $overdueCount,
            ];
        }

        return $stats;
    }

    /**
     * Grades a batch of OFFLINE submissions in one authorized, audited call, reusing
     * grade()'s scoring logic per student rather than duplicating it. Offline hand-ins
     * created via markReceived()/markReceivedForStudent() never have `is_late` set, so
     * applyLatePenalty() is a no-op for them — offline lateness is a physical-world fact
     * staff can already see when they mark a hand-in received, not one this phase tracks
     * or penalizes automatically.
     *
     * @param  array<string, array{raw_score: float, feedback?: ?string}>  $gradesByStudentId  student_id => grade data
     * @return Collection<int, AssignmentSubmission>
     */
    public function bulkGradeOffline(User $actor, Assignment $assignment, array $gradesByStudentId): Collection
    {
        $this->authorize->authorize($actor, 'assignments.grade', $assignment);

        if ($assignment->delivery_mode !== DeliveryMode::Offline) {
            throw ValidationException::withMessages([
                'assignment' => [__('assessment.bulk_grade_offline_only')],
            ]);
        }

        return $this->audit->withAudit($actor, 'assignments.bulk_grade_offline', function () use ($actor, $assignment, $gradesByStudentId) {
            $graded = collect();

            foreach ($gradesByStudentId as $studentId => $entry) {
                $submission = AssignmentSubmission::query()
                    ->where('assignment_id', $assignment->id)
                    ->where('student_id', $studentId)
                    ->first();

                if ($submission === null) {
                    continue;
                }

                $rawScore = (float) ($entry['raw_score'] ?? 0);
                $feedback = $entry['feedback'] ?? null;

                $graded->push($this->grade($actor, $submission, $rawScore, $feedback));
            }

            return $graded;
        }, 'Assignment');
    }
}
