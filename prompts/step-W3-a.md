=== STEP W3-a — Course Player Polish ===

SCOPE (presentation-only — no controller, route, or migration changes):
  resources/views/learn/offering.blade.php
  resources/views/learn/week.blade.php
  resources/views/learn/item.blade.php
  resources/views/learn/partials/week-nav.blade.php
  lang/{ar,en,fr}/learn.php   (append keys only)

GOAL:
Make the student course-player feel like a real LMS, not a flat list. Three concrete improvements:

1. PROGRESS BAR on offering.blade.php
   - Replace plain-text "progress: X%" with a Bootstrap progress bar
     ```html
     <div class="progress mb-2" style="height:6px" role="progressbar"
          aria-valuenow="{{ (int)$enrollment->progress_percent }}" aria-valuemin="0" aria-valuemax="100">
       <div class="progress-bar bg-primary" style="width:{{ (int)$enrollment->progress_percent }}%"></div>
     </div>
     <p class="small spims-text-dim mb-0">{{ (int)$enrollment->progress_percent }}% {{ __('learn.complete') }}</p>
     ```
   - Use inline `style="width:X%"` ONLY on the inner `.progress-bar` — this is the only acceptable
     inline style for a data-driven width; everything else uses tokens.

2. ITEM COUNT CHIP in week-nav partial
   - For each week in the week-nav sidebar, show a small muted count "X/Y" beside the week title
     where X = completed items in that week, Y = total items.
   - The week-nav already receives `$completedItemIds` — use it.
   - Add the chip as `<span class="badge text-bg-info ms-1">X / Y</span>` after the week title text.
   - Do NOT change the nav's link structure or the active-week highlight logic.

3. PREV / NEXT NAVIGATION on item.blade.php
   - The item view already has `$activeWeek` and `$item`. The week has `$activeWeek->items` (sorted
     by `order`).
   - Compute prev/next in a `@php` block (no controller change):
     ```php
     $sortedItems = $activeWeek->items->sortBy('order')->values();
     $idx = $sortedItems->search(fn($i) => $i->id === $item->id);
     $prevItem = $idx > 0 ? $sortedItems[$idx - 1] : null;
     $nextItem = $idx < $sortedItems->count() - 1 ? $sortedItems[$idx + 1] : null;
     ```
   - Render a `d-flex justify-content-between mt-3` row with:
     - Left: `<a>` to prev item (or disabled span if none) labelled `__('learn.prev_item')`
     - Right: `<a>` to next item (or disabled span if none) labelled `__('learn.next_item')`
   - Both links route to `route('learn.item', [$offering, $prevItem])` etc.

LANG KEYS TO ADD (all three locales, ar + en + fr):
  learn.complete          → "complete" / "مكتمل" / "terminé"
  learn.prev_item         → "← Previous" / "→ السابق" (RTL: Arabic arrow flips) / "← Précédent"
  learn.next_item         → "Next →" / "التالي ←" / "Suivant →"
  learn.week_progress     → "X / Y items" (not needed as a lang key — compute inline)

RULES:
- Presentation-only. Do NOT touch any controller, route, or migration.
- Every string goes in lang/{ar,en,fr}/learn.php.
- No banned patterns (text-muted, bg-light, bg-secondary, card border-0 shadow-sm, raw ->value in output).
- Mobile-first: the prev/next row must be usable at 360px (full width, stacked if needed).
- RTL: use logical CSS (margin-inline-start, etc.) for any directional spacing.

DONE WHEN:
- `./scripts/validate-step.sh W3-a` exits 0 (PASS).
- All three changes are visible in the Blade output.
- Committed and pushed to the worktree branch.
