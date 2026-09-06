<?php

namespace App\Services\Completion;

use App\Enums\CompletionOutcome;
use App\Enums\OfferingClosingStatus;
use App\Models\CompletionResult;
use App\Models\CourseOffering;
use App\Models\OfferingClosing;
use App\Models\User;
use App\Services\Communications\AnnouncementService;
use App\Services\Credentials\CredentialService;
use App\Services\Gradebook\GradebookService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Validation\ValidationException;

/**
 * The offering "closing ceremony" (G-13): OPEN -> GRADING_LOCKED -> ANNOUNCED
 * -> CLOSED, strictly forward — never backward, never skipping a state.
 */
class OfferingClosingService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly GradebookService $gradebook,
        private readonly CompletionService $completion,
        private readonly AnnouncementService $announcements,
        private readonly CredentialService $credentials,
    ) {}

    /**
     * Delegates the actual grade-locking to the existing
     * GradebookService::lockGrades() — this method only owns the S4 closing
     * status, never grade computation.
     */
    public function lockGrading(User $actor, CourseOffering $offering): OfferingClosing
    {
        $this->authorize->authorize($actor, 'offering.close', $offering);

        $closing = $this->closingFor($offering);
        $this->assertAdvance($closing->status, OfferingClosingStatus::GradingLocked);

        $this->gradebook->lockGrades($actor, $offering);

        return $this->audit->withAudit($actor, 'offering_closing.lock_grading', function () use ($closing, $actor) {
            $closing->update([
                'status' => OfferingClosingStatus::GradingLocked,
                'locked_at' => now(),
                'locked_by_id' => $actor->id,
            ]);

            return $closing->fresh();
        }, 'OfferingClosing');
    }

    /**
     * Grace marks are only honest while grading is locked but not yet
     * announced — applying them after ANNOUNCED would let staff quietly change
     * a result students have already been told about.
     *
     * Design decision: the grace amount is added to the enrollment's computed
     * `final_percent` for MIN_GRADE criteria evaluation ONLY (see
     * CompletionService::passesMinGrade()) — it never mutates the locked
     * Enrollment row itself. `$graceMarksByStudentId` REPLACES the stored map
     * wholesale on each call (it is not merged with any prior map); callers
     * must submit the complete set of grace marks they want in effect.
     *
     * Re-triggers CompletionService::evaluate() so completion_results reflect
     * the adjustment immediately.
     *
     * @param  array<string, float>  $graceMarksByStudentId
     */
    public function applyGraceMarks(User $actor, CourseOffering $offering, array $graceMarksByStudentId): OfferingClosing
    {
        $this->authorize->authorize($actor, 'offering.close', $offering);

        $closing = $this->closingFor($offering);

        if ($closing->status !== OfferingClosingStatus::GradingLocked) {
            throw ValidationException::withMessages([
                'status' => [__('completion.grace_marks_requires_locked')],
            ]);
        }

        $updated = $this->audit->withAudit($actor, 'offering_closing.apply_grace_marks', function () use ($closing, $graceMarksByStudentId) {
            $closing->update(['grace_marks' => $graceMarksByStudentId]);

            return $closing->fresh();
        }, 'OfferingClosing');

        $this->completion->evaluate($actor, $offering);

        return $updated;
    }

    /**
     * Only valid from GRADING_LOCKED. Dispatches a cohort notification to
     * every enrolled student through S2's real publish path
     * (AnnouncementService::draft() + publish(), which fans out through
     * ChannelDispatcher) rather than inventing a new notification mechanism.
     * Guarded on current status: a retried request while already ANNOUNCED is
     * a no-op that returns the existing record without re-dispatching.
     */
    public function announce(User $actor, CourseOffering $offering): OfferingClosing
    {
        $this->authorize->authorize($actor, 'offering.close', $offering);

        $closing = $this->closingFor($offering);

        if ($closing->status === OfferingClosingStatus::Announced) {
            return $closing;
        }

        $this->assertAdvance($closing->status, OfferingClosingStatus::Announced);

        return $this->audit->withAudit($actor, 'offering_closing.announce', function () use ($closing, $actor, $offering) {
            $offering->loadMissing('course');

            $announcement = $this->announcements->draft($actor, $offering, [
                'title' => __('completion.closing_announcement_title', ['course' => $offering->course->title]),
                'body' => __('completion.closing_announcement_body', ['course' => $offering->course->title]),
            ]);
            $this->announcements->publish($actor, $announcement);

            $closing->update([
                'status' => OfferingClosingStatus::Announced,
                'announced_at' => now(),
                'announced_by_id' => $actor->id,
            ]);

            return $closing->fresh();
        }, 'OfferingClosing');
    }

    /**
     * Only valid from ANNOUNCED. Issues a credential for every student whose
     * completion_results.outcome is COMPLETED via
     * CredentialService::issueOfferingCompletion(), which is itself
     * idempotency-guarded on (student_id, offering_id, type) — so closing
     * twice, or retrying after a crash mid-transaction, never issues a second
     * credential per student. Guarded on current status: a retried request
     * while already CLOSED is a no-op.
     */
    public function close(User $actor, CourseOffering $offering): OfferingClosing
    {
        $this->authorize->authorize($actor, 'offering.close', $offering);
        // Instructors may lock and announce (`offering.close` = O) but issuing
        // credentials is an academic-admin action — credentials.issue is F/issue
        // for the admin tier only.
        $this->authorize->authorize($actor, 'credentials.issue');

        $closing = $this->closingFor($offering);

        if ($closing->status === OfferingClosingStatus::Closed) {
            return $closing;
        }

        $this->assertAdvance($closing->status, OfferingClosingStatus::Closed);

        return $this->audit->withAudit($actor, 'offering_closing.close', function () use ($closing, $actor, $offering) {
            $completed = CompletionResult::query()
                ->where('offering_id', $offering->id)
                ->where('outcome', CompletionOutcome::Completed->value)
                ->with('student')
                ->get();

            foreach ($completed as $result) {
                if ($result->student === null) {
                    continue;
                }

                $this->credentials->issueOfferingCompletion(
                    $actor,
                    $result->student,
                    $offering,
                    $result->student->preferred_locale ?: 'en',
                );
            }

            $closing->update([
                'status' => OfferingClosingStatus::Closed,
                'closed_at' => now(),
                'closed_by_id' => $actor->id,
            ]);

            return $closing->fresh();
        }, 'OfferingClosing');
    }

    public function statusFor(CourseOffering $offering): OfferingClosingStatus
    {
        return OfferingClosing::query()->where('offering_id', $offering->id)->first()?->status
            ?? OfferingClosingStatus::Open;
    }

    private function closingFor(CourseOffering $offering): OfferingClosing
    {
        return OfferingClosing::query()->firstOrCreate(
            ['offering_id' => $offering->id],
            ['status' => OfferingClosingStatus::Open->value]
        );
    }

    private function assertAdvance(OfferingClosingStatus $current, OfferingClosingStatus $target): void
    {
        if (! $current->canAdvanceTo($target)) {
            throw ValidationException::withMessages([
                'status' => [__('completion.invalid_transition', ['from' => $current->value, 'to' => $target->value])],
            ]);
        }
    }
}
