<?php

namespace App\Services\Offerings;

use App\Enums\ContentItemType;
use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\EnrollmentItemCompletion;
use App\Models\EnrollmentWeekCompletion;
use App\Models\User;
use App\Models\Week;
use App\Support\AuditLogWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearningProgressService
{
    public function __construct(
        private readonly AuditLogWriter $audit,
        private readonly ContentGatingService $gating,
    ) {}

    /**
     * @return list<int>
     */
    public function completedWeekNumbers(Enrollment $enrollment): array
    {
        return EnrollmentWeekCompletion::query()
            ->where('enrollment_id', $enrollment->id)
            ->with('week:id,number')
            ->get()
            ->pluck('week.number')
            ->filter()
            ->map(fn ($n) => (int) $n)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function completedItemIds(Enrollment $enrollment): array
    {
        return EnrollmentItemCompletion::query()
            ->where('enrollment_id', $enrollment->id)
            ->pluck('content_item_id')
            ->all();
    }

    public function isItemComplete(Enrollment $enrollment, ContentItem $item): bool
    {
        return EnrollmentItemCompletion::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('content_item_id', $item->id)
            ->exists();
    }

    public function isWeekComplete(Enrollment $enrollment, Week $week): bool
    {
        return EnrollmentWeekCompletion::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('week_id', $week->id)
            ->exists();
    }

    public function isWeekUnlocked(Enrollment $enrollment, CourseOffering $offering, Week $week): bool
    {
        return $this->gating->isWeekUnlocked(
            $offering,
            $week,
            enrolled: true,
            completedWeekNumbers: $this->completedWeekNumbers($enrollment),
        );
    }

    public function markItemComplete(User $actor, Enrollment $enrollment, ContentItem $item, bool $manual = false): EnrollmentItemCompletion
    {
        if ($enrollment->student_id !== $actor->id && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages(['learn' => [__('learn.access_denied')]]);
        }

        $week = $item->week()->with('offering')->firstOrFail();
        $offering = $week->offering;

        if ($offering->id !== $enrollment->offering_id) {
            throw ValidationException::withMessages(['learn' => [__('learn.item_mismatch')]]);
        }

        if (! $this->isWeekUnlocked($enrollment, $offering, $week)) {
            throw ValidationException::withMessages(['learn' => [__('learn.week_locked')]]);
        }

        if ($manual && ! in_array($item->type, [ContentItemType::Video, ContentItemType::Reading, ContentItemType::Text], true)) {
            throw ValidationException::withMessages(['learn' => [__('learn.manual_complete_only_passive')]]);
        }

        return $this->audit->withAudit($actor, 'learn.item_complete', function () use ($enrollment, $item, $week) {
            $completion = EnrollmentItemCompletion::query()->firstOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'content_item_id' => $item->id,
                ],
                ['completed_at' => now()]
            );

            $this->maybeCompleteWeek($enrollment, $week);
            $this->recomputeProgress($enrollment);

            return $completion;
        }, 'EnrollmentItemCompletion');
    }

    /**
     * Record completion for an item linked to an assignment/assessment without week-lock UI checks
     * (caller already verified enrollment for the activity).
     */
    public function recordLinkedItemComplete(User $student, ContentItem $item): void
    {
        $week = $item->week()->first();
        if (! $week) {
            return;
        }

        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('offering_id', $week->offering_id)
            ->whereIn('status', ['ENROLLED', 'COMPLETED'])
            ->first();

        if (! $enrollment) {
            return;
        }

        DB::transaction(function () use ($student, $enrollment, $item, $week) {
            EnrollmentItemCompletion::query()->firstOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'content_item_id' => $item->id,
                ],
                ['completed_at' => now()]
            );

            $this->maybeCompleteWeek($enrollment, $week);
            $this->recomputeProgress($enrollment);

            $this->audit->write(
                $student,
                'learn.item_complete_auto',
                'EnrollmentItemCompletion',
                $item->id,
                after: ['enrollment_id' => $enrollment->id, 'content_item_id' => $item->id]
            );
        });
    }

    public function maybeCompleteWeek(Enrollment $enrollment, Week $week): void
    {
        $itemIds = ContentItem::query()->where('week_id', $week->id)->pluck('id');
        if ($itemIds->isEmpty()) {
            return;
        }

        $done = EnrollmentItemCompletion::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('content_item_id', $itemIds)
            ->count();

        if ($done < $itemIds->count()) {
            return;
        }

        EnrollmentWeekCompletion::query()->firstOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'week_id' => $week->id,
            ],
            ['completed_at' => now()]
        );
    }

    public function recomputeProgress(Enrollment $enrollment): void
    {
        $offeringId = $enrollment->offering_id;
        $total = ContentItem::query()
            ->whereHas('week', fn ($q) => $q->where('offering_id', $offeringId))
            ->count();

        if ($total === 0) {
            $enrollment->update(['progress_percent' => 0]);

            return;
        }

        $completed = EnrollmentItemCompletion::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn(
                'content_item_id',
                ContentItem::query()
                    ->whereHas('week', fn ($q) => $q->where('offering_id', $offeringId))
                    ->select('id')
            )
            ->count();

        $enrollment->update([
            'progress_percent' => round(100 * ($completed / $total), 2),
        ]);
    }
}
