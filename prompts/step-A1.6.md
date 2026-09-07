# Step A1.6 — Documentation, Icon Vocabulary & Route Anchors

Step ID: `A1.6`  
Wave: W1 (sequential — last A1 substep)  
Scope: docs/design-system.md, routes/web.php  
Prerequisite: A1.1–A1.5 committed.

## Goal
Write the documentation every future agent reads, establish the canonical icon vocabulary, and
add route anchors so lanes stop colliding.

## What to build

### Icon vocabulary
The portal already has **92 Bootstrap Icon instances** across 57 Blade files. Your job is to
UNIFY the existing use, not introduce new icons.

Run: `grep -rh 'bi-[a-z-]*' resources/views --include="*.blade.php" -o | sort | uniq -c | sort -rn | head -40`
to see the most-used icons. For each domain concept that already has a consistent icon, adopt it.
For concepts that use multiple different icons inconsistently, pick one and document it.

Produce: a vocabulary table mapping concept → `bi-*` class, e.g.:
```
course       → bi-journal-text
student      → bi-person
enrollment   → bi-person-check
grade        → bi-star-half
finance      → bi-wallet2
assessment   → bi-clipboard-check
attendance   → bi-calendar-check
credential   → bi-award
settings     → bi-gear
delete       → bi-trash          (always icon + text, never icon alone)
add          → bi-plus-circle
edit         → bi-pencil
view/open    → bi-eye
download     → bi-download
warning      → bi-exclamation-triangle
success      → bi-check-circle
empty-state  → bi-inbox          (default; individual empty states may override)
```
Seed `<x-icon>` vocabulary map (started in A1.3) with the complete list.

### `docs/design-system.md` (create or complete)
Sections (in order):
1. **Tokens** — every `--color-*`, `--font-*`, `--radius-*`, `--shadow-*`, `--space-*`, `--text-*`, `--focus-ring`, `--motion-*` with one-line description
2. **Surface Tiers** — panel / quiet / bare with when-to-use and Blade examples
3. **Component Library** — one subsection per component: required props, optional props, a copy-pasteable Blade example
4. **Icon Vocabulary** — the full concept→icon table
5. **Responsive Rules** — mobile-first grid, the breakpoints, data table rule, form rule
6. **Migration Table** — mapping every legacy pattern to its replacement:
   ```
   | Legacy                       | Replacement                            |
   | card border-0 shadow-sm      | <x-card variant="panel">               |
   | bare <h1>                    | <x-page-header :title="...">           |
   | text-muted                   | class with --color-text-muted token     |
   | bg-light                     | class with --color-bg-1 token           |
   | bg-secondary                 | class with --color-surface-low token    |
   | $amount_minor }}             | <x-money :minor="$amount_minor" ...>   |
   | $status->value }}            | <x-badge :value="$status">             |
   | inline style color:          | token class from the design system      |
   | col-md-* without unprefixed  | add unprefixed col-* for phone          |
   | >4-column table              | <x-data-table> with columns prop        |
   ```
7. **Design Review Checklist** — human-readable checklist for PR reviewers:
   - [ ] Three surface tiers visible and distinct
   - [ ] 360/768/1440 all render without horizontal scroll
   - [ ] Light and dark both look right
   - [ ] Arabic RTL: stems on correct side, text-align logical
   - [ ] No raw enum visible in any locale
   - [ ] No raw minor integer visible in any locale

### Route anchors in `routes/web.php`
Add track anchor comments so every lane appends at a known line. Do NOT reorder existing routes —
insert comment markers only. Format:
```php
// --- TRACK: public-marketing (Steps 1-4) ---
// --- TRACK: semester-calendar (Step 5) ---
// --- TRACK: api-parity-events (Step 9) ---
// --- TRACK: api-parity-surveys (Step 10) ---
// --- TRACK: api-parity-livequiz (Step 11) ---
// --- TRACK: api-parity-projects (Step 12) ---
// --- TRACK: instructor-grading (Step 13) ---
// --- TRACK: demo (Steps 8A/8B) ---
```

## Gate (`tests/Feature/Design/DesignDocsTest.php` — create it):
- `docs/design-system.md` exists
- It contains a section heading for every component actually present in `resources/views/components/`
- The migration table lists every banned pattern from `guard-design.sh` (grep the hook to get the list)
- `routes/web.php` contains each track anchor exactly once (no duplicates, none missing)

Write `echo "A1.6" > .claude/current-step` then `./scripts/validate-step.sh A1.6`.

---

## After A1.6: ⛔ HUMAN DESIGN REVIEW GATE

Before W1.5 (A3) starts:
1. Read `docs/design-system.md` completely.
2. Render a scratch Blade view using each surface tier, each component, and icons from the vocabulary.
3. View at 360px / 768px / 1440px × light / dark / RTL.
4. Confirm: the three surface tiers actually look visually different from one another.
5. Confirm: icons are used consistently with the vocabulary.
6. Confirm: type hierarchy is visible (headings larger than body, body readable at 360px).

If A1 is wrong, 21 downstream steps inherit it. This review is ~30 minutes and happens once.
