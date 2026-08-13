<?php

namespace App\Services\Learning;

use App\Enums\ContentItemType;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\EnrollmentWeekCompletion;
use App\Models\User;
use App\Models\Week;
use App\Services\Offerings\ContentGatingService;
use App\Support\AuditLogWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CoursePlayerService
{
    public function __construct(
        private readonly OfferingAccessService $access,
        private readonly ContentGatingService $gating,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @return array{enrollment: Enrollment, offering: CourseOffering, weeks: array<int, array<string, mixed>>, completed: array<int, int>, progress: float, announcements: \Illuminate\Support\Collection}
     */
    public function playerPayload(User $user, CourseOffering $offering): array
    {
        $this->access->assertCanAccessOffering($user, $offering);

        $enrollment = $this->access->enrollmentFor($user, $offering);
        $isStaff = $this->access->isStaffOrAdmin($user, $offering);

        $offering->load(['course', 'weeks.items', 'semester']);

        $completed = [];
        if ($enrollment !== null) {
            $completed = EnrollmentWeekCompletion::query()
                ->where('enrollment_id', $enrollment->id)
                ->with('week:id,number')
                ->get()
                ->pluck('week.number')
                ->filter()
                ->map(fn ($n) => (int) $n)
                ->values()
                ->all();
        }

        $weeks = $offering->weeks->sortBy('number')->values()->map(function (Week $week) use ($offering, $enrollment, $completed, $isStaff) {
            $unlocked = $isStaff || $this->gating->isWeekUnlocked(
                $offering,
                $week,
                enrolled: $enrollment !== null || $isStaff,
                completedWeekNumbers: $completed,
            );

            return [
                'id' => $week->id,
                'number' => $week->number,
                'title' => $week->title,
                'unlocked' => $unlocked,
                'completed' => in_array($week->number, $completed, true),
                'items' => $unlocked
                    ? $week->items->sortBy('order')->values()->map(fn ($item) => $this->mapItem($item, $offering))->all()
                    : [],
            ];
        })->all();

        $progress = 0.0;
        $totalWeeks = max(1, count($weeks));
        if ($enrollment !== null) {
            $progress = round((count($completed) / $totalWeeks) * 100, 1);
        }

        return [
            'enrollment' => $enrollment,
            'offering' => $offering,
            'weeks' => $weeks,
            'completed' => $completed,
            'progress' => $progress,
            'announcements' => Announcement::query()
                ->where('offering_id', $offering->id)
                ->latest('created_at')
                ->limit(10)
                ->get(),
            'is_staff' => $isStaff,
        ];
    }

    public function completeWeek(User $user, CourseOffering $offering, Week $week): Enrollment
    {
        $enrollment = $this->access->enrollmentFor($user, $offering);
        if ($enrollment === null) {
            throw ValidationException::withMessages(['week' => [__('learning.not_enrolled')]]);
        }

        if ($week->offering_id !== $offering->id) {
            throw ValidationException::withMessages(['week' => [__('auth.forbidden')]]);
        }

        $completed = EnrollmentWeekCompletion::query()
            ->where('enrollment_id', $enrollment->id)
            ->with('week:id,number')
            ->get()
            ->pluck('week.number')
            ->map(fn ($n) => (int) $n)
            ->all();

        if (! $this->gating->isWeekUnlocked($offering, $week, true, $completed)) {
            throw ValidationException::withMessages(['week' => [__('learning.week_locked')]]);
        }

        return DB::transaction(function () use ($user, $enrollment, $week, $offering) {
            EnrollmentWeekCompletion::query()->firstOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'week_id' => $week->id,
                ],
                ['completed_at' => now()]
            );

            $totalWeeks = max(1, $offering->weeks()->count());
            $done = EnrollmentWeekCompletion::query()->where('enrollment_id', $enrollment->id)->count();
            $enrollment->update([
                'progress_percent' => round(($done / $totalWeeks) * 100, 1),
            ]);

            $this->audit->write($user, 'learning.week_complete', 'Enrollment', $enrollment->id, null, [
                'week_id' => $week->id,
                'week_number' => $week->number,
            ]);

            return $enrollment->fresh();
        });
    }

    /**
     * @param  \App\Models\ContentItem  $item
     * @return array<string, mixed>
     */
    private function mapItem($item, CourseOffering $offering): array
    {
        $payload = [
            'id' => $item->id,
            'type' => $item->type->value,
            'title' => $item->title,
            'vimeo_id' => $item->vimeo_id,
            'file_url' => $item->file_url,
            'body' => $item->body,
            'url' => null,
        ];

        if (in_array($item->type, [ContentItemType::Assignment], true)) {
            $assignment = Assignment::query()->where('content_item_id', $item->id)->first();
            $payload['url'] = $assignment ? route('assignments.show', $assignment) : null;
        }

        if (in_array($item->type, [ContentItemType::Quiz, ContentItemType::Exam], true)) {
            $assessment = Assessment::query()->where('content_item_id', $item->id)->first();
            if ($assessment === null) {
                $assessment = Assessment::query()
                    ->where('offering_id', $offering->id)
                    ->where('title', $item->title)
                    ->first();
            }
            if ($assessment === null) {
                $assessment = Assessment::query()
                    ->where('offering_id', $offering->id)
                    ->where('released', true)
                    ->orderBy('created_at')
                    ->first();
            }
            $payload['url'] = $assessment ? route('assessments.show', $assessment) : null;
        }

        if ($item->type === ContentItemType::Discussion) {
            $payload['url'] = route('discussions.board', $offering);
        }

        return $payload;
    }
}
