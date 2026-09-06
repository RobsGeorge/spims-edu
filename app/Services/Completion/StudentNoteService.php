<?php

namespace App\Services\Completion;

use App\Exceptions\AuthorizationException;
use App\Models\CourseOffering;
use App\Models\StudentNote;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;

/**
 * Instructor/TA notes about a student on an offering (G-16). `student_notes`
 * are staff-only: a student must never be able to read notes about
 * themselves, enforced here in the read path (not just hidden in the UI), and
 * never exposed on the student's own API surface either.
 */
class StudentNoteService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @return Collection<int, StudentNote>
     */
    public function forStudent(User $actor, CourseOffering $offering, User $student): Collection
    {
        $this->assertNotSelf($actor, $student);
        $this->authorize->authorize($actor, 'student_notes.view', $offering);

        return StudentNote::query()
            ->where('offering_id', $offering->id)
            ->where('student_id', $student->id)
            ->with('author')
            ->orderByDesc('created_at')
            ->get();
    }

    public function add(User $actor, CourseOffering $offering, User $student, string $body): StudentNote
    {
        $this->assertNotSelf($actor, $student);
        $this->authorize->authorize($actor, 'student_notes.manage', $offering);

        return $this->audit->withAudit($actor, 'student_notes.create', function () use ($offering, $student, $actor, $body) {
            return StudentNote::query()->create([
                'offering_id' => $offering->id,
                'student_id' => $student->id,
                'author_id' => $actor->id,
                'body' => $body,
                'visibility' => StudentNote::VISIBILITY_STAFF_ONLY,
            ]);
        }, StudentNote::class);
    }

    /**
     * Rejected before the permission check runs, so a student who happens to
     * also hold staff on some other offering still cannot read their own notes
     * by naming themselves as the subject.
     */
    private function assertNotSelf(User $actor, User $student): void
    {
        if ($actor->id === $student->id) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }
}
