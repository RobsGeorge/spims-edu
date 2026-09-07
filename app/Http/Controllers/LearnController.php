<?php

namespace App\Http\Controllers;

use App\Enums\ContentItemType;
use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Week;
use App\Services\Discussions\DiscussionService;
use App\Services\Learning\StudentPreviewService;
use App\Services\Offerings\ContentGatingService;
use App\Services\Offerings\LearningAccessService;
use App\Services\Offerings\LearningProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearnController extends Controller
{
    public function __construct(
        private readonly LearningAccessService $access,
        private readonly LearningProgressService $progress,
        private readonly DiscussionService $discussions,
        private readonly StudentPreviewService $preview,
        private readonly ContentGatingService $gating,
    ) {}

    public function offering(Request $request, CourseOffering $offering): View
    {
        [$enrollment, $studentPreview] = $this->viewer($request, $offering);
        $offering->load(['course', 'weeks.items.assignment', 'weeks.items.assessment']);
        $this->hideDraftItems($offering);

        $weeks = $offering->weeks->sortBy('number')->values();
        $completedWeeks = $enrollment ? $this->progress->completedWeekNumbers($enrollment) : [];
        $completedItems = $enrollment ? $this->progress->completedItemIds($enrollment) : [];

        $target = $weeks->first(fn (Week $w) => $this->weekUnlocked($enrollment, $offering, $w, $completedWeeks))
            ?? $weeks->first();

        return view('learn.offering', [
            'offering' => $offering,
            'enrollment' => $enrollment,
            'studentPreview' => $studentPreview,
            'weeks' => $weeks,
            'activeWeek' => $target,
            'completedWeekNumbers' => $completedWeeks,
            'completedItemIds' => $completedItems,
            'progress' => $this->progress,
            'hasPublishedProjects' => $offering->hasPublishedProjectAssessments(),
        ]);
    }

    public function week(Request $request, CourseOffering $offering, Week $week): View
    {
        [$enrollment, $studentPreview] = $this->viewer($request, $offering);
        $this->access->assertWeekBelongsToOffering($week, $offering);
        $offering->load(['course', 'weeks.items.assignment', 'weeks.items.assessment']);
        $week->load(['items.assignment', 'items.assessment']);
        $this->hideDraftItems($offering);
        $week->setRelation('items', $week->items->filter(fn ($item) => $item->isPublished())->values());

        $completedWeeks = $enrollment ? $this->progress->completedWeekNumbers($enrollment) : [];

        return view('learn.week', [
            'offering' => $offering,
            'enrollment' => $enrollment,
            'studentPreview' => $studentPreview,
            'weeks' => $offering->weeks->sortBy('number')->values(),
            'activeWeek' => $week,
            'unlocked' => $this->weekUnlocked($enrollment, $offering, $week, $completedWeeks),
            'completedWeekNumbers' => $completedWeeks,
            'completedItemIds' => $enrollment ? $this->progress->completedItemIds($enrollment) : [],
            'progress' => $this->progress,
        ]);
    }

    public function item(Request $request, CourseOffering $offering, ContentItem $item): View|RedirectResponse
    {
        [$enrollment, $studentPreview] = $this->viewer($request, $offering);
        $week = $this->access->assertItemBelongsToOffering($item, $offering);
        $offering->load(['course', 'weeks.items.assignment', 'weeks.items.assessment']);
        $item->load(['assignment', 'assessment']);

        if (! $item->isPublished()) {
            abort(404);
        }

        $completedWeeks = $enrollment ? $this->progress->completedWeekNumbers($enrollment) : [];
        if (! $this->weekUnlocked($enrollment, $offering, $week, $completedWeeks)) {
            return redirect()
                ->route('learn.week', [$offering, $week])
                ->withErrors(['learn' => __('learn.week_locked')]);
        }

        if ($studentPreview && in_array($item->type, [ContentItemType::Assignment, ContentItemType::Quiz, ContentItemType::Exam, ContentItemType::Discussion], true)) {
            return redirect()->route('learn.week', [$offering, $week])
                ->withErrors(['learn' => __('offerings.preview_read_only')]);
        }

        if (in_array($item->type, [ContentItemType::Assignment, ContentItemType::Quiz, ContentItemType::Exam, ContentItemType::Discussion], true)) {
            return $this->deepLink($request, $offering, $item, $enrollment);
        }

        return view('learn.item', [
            'offering' => $offering,
            'enrollment' => $enrollment,
            'studentPreview' => $studentPreview,
            'weeks' => $offering->weeks->sortBy('number')->values(),
            'activeWeek' => $week,
            'item' => $item,
            'completed' => $enrollment ? $this->progress->isItemComplete($enrollment, $item) : false,
            'completedWeekNumbers' => $completedWeeks,
            'completedItemIds' => $enrollment ? $this->progress->completedItemIds($enrollment) : [],
            'progress' => $this->progress,
        ]);
    }

    public function complete(Request $request, CourseOffering $offering, ContentItem $item): RedirectResponse
    {
        $this->preview->assertNotPreview($request, $offering);
        $enrollment = $this->access->requireEnrollment($request->user(), $offering);
        $this->access->assertItemBelongsToOffering($item, $offering);

        $this->progress->markItemComplete($request->user(), $enrollment, $item, manual: true);

        return redirect()
            ->route('learn.item', [$offering, $item])
            ->with('status', __('learn.marked_complete'));
    }

    private function deepLink(Request $request, CourseOffering $offering, ContentItem $item, $enrollment): RedirectResponse
    {
        if ($item->type === ContentItemType::Discussion) {
            $this->progress->markItemComplete($request->user(), $enrollment, $item, manual: false);

            $this->discussions->provisionBoard($request->user(), $offering);

            return redirect()->route('discussions.board', $offering);
        }

        if ($item->type === ContentItemType::Assignment) {
            $assignment = $item->assignment;
            if (! $assignment) {
                return redirect()
                    ->route('learn.week', [$offering, $item->week_id])
                    ->withErrors(['learn' => __('learn.link_missing')]);
            }

            return redirect()->route('assignments.show', $assignment);
        }

        if (in_array($item->type, [ContentItemType::Quiz, ContentItemType::Exam], true)) {
            $assessment = $item->assessment;
            if (! $assessment) {
                return redirect()
                    ->route('learn.week', [$offering, $item->week_id])
                    ->withErrors(['learn' => __('learn.link_missing')]);
            }

            return redirect()->route('assessments.show', $assessment);
        }

        return redirect()->route('learn.offering', $offering);
    }

    /**
     * @return array{0: ?Enrollment, 1: bool}
     */
    private function viewer(Request $request, CourseOffering $offering): array
    {
        if ($this->preview->isActive($request, $offering)) {
            return [null, true];
        }

        return [$this->access->requireEnrollment($request->user(), $offering), false];
    }

    private function weekUnlocked(?Enrollment $enrollment, CourseOffering $offering, Week $week, array $completedWeeks): bool
    {
        if ($enrollment !== null) {
            return $this->progress->isWeekUnlocked($enrollment, $offering, $week);
        }

        return $this->gating->isWeekUnlocked($offering, $week, enrolled: true, completedWeekNumbers: $completedWeeks);
    }

    private function hideDraftItems(CourseOffering $offering): void
    {
        foreach ($offering->weeks as $week) {
            $week->setRelation(
                'items',
                $week->items->filter(fn (ContentItem $item) => $item->isPublished())->values()
            );
        }
    }
}
