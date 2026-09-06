<?php

namespace App\Services\Assessment;

use App\Enums\AttemptStatus;
use App\Models\AssessmentAttempt;
use App\Models\ProctorEvent;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records typed proctor events during a timed attempt and escalates to automatic
 * termination once a configurable threshold is crossed. This is the "real teeth" the
 * plan doc asks for on top of the pre-existing `focus_loss_count` counter, which by
 * itself never did anything with the events it tallied.
 */
class ProctorService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>|null  $details
     */
    public function recordEvent(User $student, AssessmentAttempt $attempt, string $eventType, ?array $details = null): AssessmentAttempt
    {
        $this->assertOwner($student, $attempt);

        if ($attempt->status !== AttemptStatus::InProgress) {
            return $attempt;
        }

        return DB::transaction(function () use ($student, $attempt, $eventType, $details) {
            $attempt = AssessmentAttempt::query()->where('id', $attempt->id)->lockForUpdate()->firstOrFail();

            if ($attempt->status !== AttemptStatus::InProgress) {
                return $attempt;
            }

            // focus_loss_count is the raw tally of every proctor event ever recorded;
            // proctor_warnings is the count used to decide escalation. Every event
            // recorded in this phase increments both together, so today they stay
            // numerically identical — the split exists so a future event type could be
            // logged without counting toward escalation.
            $warningNumber = $attempt->proctor_warnings + 1;

            ProctorEvent::query()->create([
                'attempt_id' => $attempt->id,
                'student_id' => $student->id,
                'event_type' => $eventType,
                'warning_number' => $warningNumber,
                'details' => $details,
            ]);

            $attempt->increment('focus_loss_count');
            $attempt->proctor_warnings = $warningNumber;

            $threshold = (int) config('assessment.proctor_termination_threshold', 5);

            if ($warningNumber >= $threshold) {
                $attempt->status = AttemptStatus::Terminated;
                $attempt->terminated_for_cheating = true;
                $attempt->terminated_at = now();
                // No human actor decided this — the student's own repeated actions
                // triggered automatic termination, so terminated_by_id stays null and
                // the student is recorded as the audit actor below.
                $attempt->terminated_by_id = null;
                $attempt->save();

                $this->audit->write($student, 'assessments.attempt_terminated', 'AssessmentAttempt', $attempt->id);
            } else {
                $attempt->save();
            }

            return $attempt->fresh();
        });
    }

    /**
     * Admin override for a false-positive termination. Clears the cheating flag so the
     * attempt and its answers become gradeable again, but deliberately does NOT revert
     * `status` to IN_PROGRESS: closing an exam window doesn't reopen just because a flag
     * was cleared, so the attempt stays non-resumable for further student answering —
     * only grading becomes possible again. `terminated_at`/`terminated_by_id` are kept
     * as the historical record of the original termination.
     */
    public function clearTermination(User $actor, AssessmentAttempt $attempt): AssessmentAttempt
    {
        $this->authorize->authorize($actor, 'assessments.clear_termination');

        if (! $attempt->terminated_for_cheating) {
            throw ValidationException::withMessages([
                'attempt' => [__('assessment.not_terminated')],
            ]);
        }

        $attempt->update([
            'terminated_for_cheating' => false,
            'termination_cleared_at' => now(),
            'termination_cleared_by_id' => $actor->id,
        ]);

        $this->audit->write($actor, 'assessments.clear_termination', 'AssessmentAttempt', $attempt->id);

        return $attempt->fresh();
    }

    private function assertOwner(User $student, AssessmentAttempt $attempt): void
    {
        if ($attempt->student_id !== $student->id && ! $student->isSuperAdmin()) {
            throw ValidationException::withMessages(['attempt' => [__('assessment.not_owner')]]);
        }
    }
}
