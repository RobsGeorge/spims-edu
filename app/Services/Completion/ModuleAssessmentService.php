<?php

namespace App\Services\Completion;

use App\Models\ModuleStudentAssessment;
use App\Models\User;
use App\Models\Week;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;

/**
 * Per-week, per-student module assessment (G-16): a 1-5 rating plus an
 * optional comment, entered by staff on the roster drill-down.
 */
class ModuleAssessmentService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @return Collection<int, ModuleStudentAssessment>
     */
    public function forWeek(User $actor, Week $week): Collection
    {
        $this->authorize->authorize($actor, 'module_assessment.view', $week);

        return ModuleStudentAssessment::query()
            ->where('week_id', $week->id)
            ->with(['student', 'assessedBy'])
            ->get();
    }

    /**
     * Re-assessing the same (week, student) updates the existing row in place
     * — unique(week_id, student_id) at the DB level makes this an upsert, not
     * an accumulating history.
     */
    public function rate(User $actor, Week $week, User $student, int $rating, ?string $comment = null): ModuleStudentAssessment
    {
        $this->authorize->authorize($actor, 'module_assessment.manage', $week);

        $rating = max(ModuleStudentAssessment::RATING_MIN, min(ModuleStudentAssessment::RATING_MAX, $rating));

        return $this->audit->withAudit($actor, 'module_assessment.rate', function () use ($week, $student, $rating, $comment, $actor) {
            return ModuleStudentAssessment::query()->updateOrCreate(
                ['week_id' => $week->id, 'student_id' => $student->id],
                ['rating' => $rating, 'comment' => $comment, 'assessed_by_id' => $actor->id]
            );
        }, ModuleStudentAssessment::class);
    }
}
