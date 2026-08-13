<?php

namespace App\Services\Assessment;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ContentItem;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use App\Enums\EnrollmentStatus;
use App\Enums\SubmissionType;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use App\Services\Offerings\LearningProgressService;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly LearningProgressService $progress,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, ContentItem $item, array $data): Assignment
    {
        $this->authorize->authorize($actor, 'assignments.manage');

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
            ]);
        }, 'Assignment');
    }

    public function submit(User $student, Assignment $assignment, ?string $textBody = null, ?string $fileUrl = null): AssignmentSubmission
    {
        $this->authorize->authorize($student, 'assignments.submit');

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

        $submission = AssignmentSubmission::query()->updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            [
                'text_body' => $textBody,
                'file_url' => $fileUrl,
                'submitted_at' => $submittedAt,
                'is_late' => (bool) $isLate,
            ]
        );

        $this->audit->write($student, 'assignments.submit', 'AssignmentSubmission', $submission->id);

        if ($item) {
            $this->progress->recordLinkedItemComplete($student, $item);
        }

        return $submission;
    }

    public function grade(User $grader, AssignmentSubmission $submission, float $rawScore, ?string $feedback = null): AssignmentSubmission
    {
        $this->authorize->authorize($grader, 'assignments.grade');

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
}
