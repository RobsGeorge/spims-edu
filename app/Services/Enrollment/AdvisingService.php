<?php

namespace App\Services\Enrollment;

use App\Enums\AdvisingHoldKind;
use App\Exceptions\AuthorizationException;
use App\Models\AdvisingHold;
use App\Models\AdvisorAssignment;
use App\Models\StudentProgram;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;

/**
 * Advising-lite. Permission keys `advising.assign`, `advising.hold`, and
 * `advising.view` are school-wide role grants — they are NOT offering-scoped
 * and must not be added to `permission_scopes.offering_scoped`.
 *
 * Instructor "O" means assigned advisee only (`advisor_assignments` row),
 * enforced here. Fail closed when no student resource is passed to an
 * advisee-scoped check. Students may use `advising.view` for their own
 * degree audit / what-if only.
 */
class AdvisingService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function assignAdvisor(User $actor, User $student, User $advisor, ?string $programId = null): AdvisorAssignment
    {
        $this->authorize->authorize($actor, 'advising.assign');

        return $this->audit->withAudit($actor, 'advising.assign', function () use ($student, $advisor, $programId) {
            return AdvisorAssignment::query()->firstOrCreate([
                'student_id' => $student->id,
                'advisor_id' => $advisor->id,
                'program_id' => $programId,
            ]);
        }, AdvisorAssignment::class);
    }

    public function placeHold(User $actor, User $student, AdvisingHoldKind $kind, string $reason): AdvisingHold
    {
        $this->assertCanHold($actor, $student);

        return $this->audit->withAudit($actor, 'advising.hold.place', function () use ($actor, $student, $kind, $reason) {
            return AdvisingHold::query()->create([
                'student_id' => $student->id,
                'kind' => $kind,
                'reason' => $reason,
                'created_by_id' => $actor->id,
            ]);
        }, AdvisingHold::class);
    }

    public function releaseHold(User $actor, AdvisingHold $hold): AdvisingHold
    {
        $hold->loadMissing('student');
        $this->assertCanHold($actor, $hold->student);

        return $this->audit->withAudit($actor, 'advising.hold.release', function () use ($actor, $hold) {
            $hold->update([
                'released_at' => now(),
                'released_by_id' => $actor->id,
            ]);

            return $hold->fresh();
        }, AdvisingHold::class);
    }

    public function hasActiveHold(User $student): bool
    {
        return AdvisingHold::query()
            ->where('student_id', $student->id)
            ->active()
            ->exists();
    }

    /**
     * @return Collection<int, AdvisingHold>
     */
    public function holdsFor(User $student): Collection
    {
        return AdvisingHold::query()
            ->where('student_id', $student->id)
            ->with(['createdBy', 'releasedBy'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, AdvisorAssignment>
     */
    public function rosterFor(User $actor): Collection
    {
        $this->assertCanOpenRoster($actor);

        $query = AdvisorAssignment::query()
            ->with(['student', 'advisor', 'program'])
            ->orderByDesc('created_at');

        if (! $actor->isSuperAdmin() && ! $this->authorize->allows($actor, 'advising.assign')) {
            $query->where('advisor_id', $actor->id);
        }

        return $query->get();
    }

    public function isAssignedAdvisor(User $advisor, User $student): bool
    {
        return AdvisorAssignment::query()
            ->where('advisor_id', $advisor->id)
            ->where('student_id', $student->id)
            ->exists();
    }

    /**
     * Roster / assign / hold pages are staff-facing. Students hold
     * `advising.view` for their own audit only and must not open these.
     */
    public function assertCanOpenRoster(User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($this->authorize->allows($actor, 'advising.assign')) {
            return;
        }

        $this->authorize->authorize($actor, 'advising.hold');
    }

    public function assertCanManageAdvisee(User $actor, ?User $student): void
    {
        $this->assertCanOpenRoster($actor);
        $this->assertStudentPassed($student);

        if ($actor->isSuperAdmin() || $this->authorize->allows($actor, 'advising.assign')) {
            return;
        }

        if (! $this->isAssignedAdvisor($actor, $student)) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }

    public function assertCanHold(User $actor, ?User $student): void
    {
        $this->assertStudentPassed($student);
        $this->authorize->authorize($actor, 'advising.hold');
        $this->assertAdviseeScope($actor, $student);
    }

    public function assertCanViewStudent(User $actor, ?User $student): void
    {
        $this->assertStudentPassed($student);
        $this->authorize->authorize($actor, 'advising.view');

        if ($actor->id === $student->id) {
            return;
        }

        $this->assertAdviseeScope($actor, $student);
    }

    public function assertCanViewStudentProgram(User $actor, StudentProgram $studentProgram): void
    {
        $studentProgram->loadMissing('student');
        $this->assertCanViewStudent($actor, $studentProgram->student);
    }

    public function allowsHold(User $actor, User $student): bool
    {
        try {
            $this->assertCanHold($actor, $student);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    /**
     * Instructor/TA grants are advisee-scoped. Admin-tier roles that already
     * passed AuthorizeService are school-wide. Fail closed without a student.
     */
    private function assertAdviseeScope(User $actor, User $student): void
    {
        if ($actor->isSuperAdmin() || $this->authorize->allows($actor, 'advising.assign')) {
            return;
        }

        if (! $this->isAssignedAdvisor($actor, $student)) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }

    private function assertStudentPassed(?User $student): void
    {
        if ($student === null) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }
}
