<?php

namespace App\Services\Learning;

use App\Enums\ContentItemType;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use App\Services\Offerings\ContentGatingService;
use App\Services\Offerings\LearningProgressService;
use App\Support\AuditLogWriter;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CoursePlayerService
{
    public function __construct(
        private readonly OfferingAccessService $access,
        private readonly ContentGatingService $gating,
        private readonly LearningProgressService $progress,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @return array{enrollment: Enrollment, offering: CourseOffering, weeks: array<int, array<string, mixed>>, completed: array<int, int>, progress: float, announcements: \Illuminate\Support\Collection, is_staff: bool, hasPublishedProjects: bool}
     */
    public function playerPayload(User $user, CourseOffering $offering): array
    {
        $this->access->assertCanAccessOffering($user, $offering);

        $enrollment = $this->access->enrollmentFor($user, $offering);
        $isStaff = $this->access->isStaffOrAdmin($user, $offering);

        $offering->load(['course', 'weeks.items.assignment', 'weeks.items.assessment', 'semester']);
        $offeringAssessments = Assessment::query()->where('offering_id', $offering->id)->get();

        $completed = [];
        if ($enrollment !== null) {
            $completed = $this->progress->completedWeekNumbers($enrollment);
        }

        $weeks = $offering->weeks->sortBy('number')->values()->map(function (Week $week) use ($offering, $enrollment, $completed, $isStaff, $offeringAssessments) {
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
                    ? $week->items
                        ->when(! $isStaff, fn ($items) => $items->filter(fn ($item) => $item->isPublished()))
                        ->sortBy('order')
                        ->values()
                        ->map(fn ($item) => $this->mapItem($item, $offering, $offeringAssessments))
                        ->all()
                    : [],
            ];
        })->all();

        $progress = 0.0;
        if ($enrollment !== null) {
            $progress = (float) $enrollment->progress_percent;
        }

        return [
            'enrollment' => $enrollment,
            'offering' => $offering,
            'weeks' => $weeks,
            'completed' => $completed,
            'progress' => $progress,
            'announcements' => Announcement::query()
                ->where('offering_id', $offering->id)
                ->where('status', \App\Enums\AnnouncementStatus::Published)
                ->latest('created_at')
                ->limit(10)
                ->get(),
            'is_staff' => $isStaff,
            'hasPublishedProjects' => $offering->hasPublishedProjectAssessments(),
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

        $completed = $this->progress->completedWeekNumbers($enrollment);

        if (! $this->gating->isWeekUnlocked($offering, $week, true, $completed)) {
            throw ValidationException::withMessages(['week' => [__('learning.week_locked')]]);
        }

        return $this->audit->withAudit($user, 'learning.week_complete', function () use ($user, $enrollment, $week) {
            $this->progress->completeRemainingItems($user, $enrollment, $week);

            return $enrollment->fresh();
        }, 'Enrollment');
    }

    /**
     * @param  \App\Models\ContentItem  $item
     * @param  Collection<int, Assessment>  $offeringAssessments
     * @return array<string, mixed>
     */
    private function mapItem($item, CourseOffering $offering, Collection $offeringAssessments): array
    {
        $payload = [
            'id' => $item->id,
            'type' => $item->type->value,
            'title' => $item->title,
            'vimeo_id' => $item->vimeo_id,
            'video_provider' => $item->video_provider?->value,
            'iframe_url' => $item->videoIframeUrl(),
            'file_url' => $item->file_url,
            'body' => $item->body,
            'url' => null,
        ];

        if (in_array($item->type, [ContentItemType::Assignment], true)) {
            $assignment = $item->assignment;
            $payload['url'] = $assignment ? route('assignments.show', $assignment) : null;
        }

        if (in_array($item->type, [ContentItemType::Quiz, ContentItemType::Exam], true)) {
            $assessment = $this->resolveAssessment($item, $offeringAssessments);
            $payload['url'] = $assessment ? route('assessments.show', $assessment) : null;
        }

        if ($item->type === ContentItemType::Discussion) {
            $payload['url'] = route('discussions.board', $offering);
        }

        return $payload;
    }

    /**
     * Link a week item to its assessment only when the relationship is unambiguous.
     * Never fall back to "first released on the offering" — that deep-links the wrong exam.
     *
     * @param  \App\Models\ContentItem  $item
     * @param  Collection<int, Assessment>  $offeringAssessments
     */
    private function resolveAssessment($item, Collection $offeringAssessments): ?Assessment
    {
        if ($item->assessment !== null) {
            return $item->assessment;
        }

        $matches = $offeringAssessments->where('title', $item->title)->values();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        return null;
    }
}
