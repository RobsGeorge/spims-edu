<?php

namespace App\Http\Controllers;

use App\Enums\ContentItemType;
use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Models\DiscussionBoard;
use App\Models\Week;
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
    ) {}

    public function offering(Request $request, CourseOffering $offering): View
    {
        $enrollment = $this->access->requireEnrollment($request->user(), $offering);
        $offering->load(['course', 'weeks.items']);

        $weeks = $offering->weeks->sortBy('number')->values();
        $completedWeeks = $this->progress->completedWeekNumbers($enrollment);
        $completedItems = $this->progress->completedItemIds($enrollment);

        $target = $weeks->first(fn (Week $w) => $this->progress->isWeekUnlocked($enrollment, $offering, $w))
            ?? $weeks->first();

        return view('learn.offering', [
            'offering' => $offering,
            'enrollment' => $enrollment->fresh(),
            'weeks' => $weeks,
            'activeWeek' => $target,
            'completedWeekNumbers' => $completedWeeks,
            'completedItemIds' => $completedItems,
            'progress' => $this->progress,
        ]);
    }

    public function week(Request $request, CourseOffering $offering, Week $week): View
    {
        $enrollment = $this->access->requireEnrollment($request->user(), $offering);
        $this->access->assertWeekBelongsToOffering($week, $offering);
        $offering->load(['course', 'weeks.items']);
        $week->load('items');

        $unlocked = $this->progress->isWeekUnlocked($enrollment, $offering, $week);

        return view('learn.week', [
            'offering' => $offering,
            'enrollment' => $enrollment->fresh(),
            'weeks' => $offering->weeks->sortBy('number')->values(),
            'activeWeek' => $week,
            'unlocked' => $unlocked,
            'completedWeekNumbers' => $this->progress->completedWeekNumbers($enrollment),
            'completedItemIds' => $this->progress->completedItemIds($enrollment),
            'progress' => $this->progress,
        ]);
    }

    public function item(Request $request, CourseOffering $offering, ContentItem $item): View|RedirectResponse
    {
        $enrollment = $this->access->requireEnrollment($request->user(), $offering);
        $week = $this->access->assertItemBelongsToOffering($item, $offering);
        $offering->load(['course', 'weeks.items']);
        $item->load(['assignment', 'assessment']);

        if (! $this->progress->isWeekUnlocked($enrollment, $offering, $week)) {
            return redirect()
                ->route('learn.week', [$offering, $week])
                ->withErrors(['learn' => __('learn.week_locked')]);
        }

        if (in_array($item->type, [ContentItemType::Assignment, ContentItemType::Quiz, ContentItemType::Exam, ContentItemType::Discussion], true)) {
            return $this->deepLink($request, $offering, $item, $enrollment);
        }

        return view('learn.item', [
            'offering' => $offering,
            'enrollment' => $enrollment->fresh(),
            'weeks' => $offering->weeks->sortBy('number')->values(),
            'activeWeek' => $week,
            'item' => $item,
            'completed' => $this->progress->isItemComplete($enrollment, $item),
            'completedWeekNumbers' => $this->progress->completedWeekNumbers($enrollment),
            'completedItemIds' => $this->progress->completedItemIds($enrollment),
            'progress' => $this->progress,
        ]);
    }

    public function complete(Request $request, CourseOffering $offering, ContentItem $item): RedirectResponse
    {
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

            DiscussionBoard::query()->firstOrCreate(
                ['offering_id' => $offering->id],
                ['allow_student_threads' => true]
            );

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
}
