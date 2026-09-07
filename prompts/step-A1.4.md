# Step A1.4 — Structural Components

Step ID: `A1.4`  
Wave: W1 (sequential)  
Scope: resources/views/components  
Prerequisite: A1.1, A1.2, A1.3 committed.

## Goal
Build the structural components that A2 migration and all later steps will consume.

## What to build

All components use `--space-*` and `--text-*` tokens from A1.1 and the surface tier system from A1.2.
No hardcoded values. All are RTL-safe (logical CSS properties). All respect `prefers-reduced-motion`.

### `<x-page-header>` — UPGRADE, keep existing API
The 67 existing views call this component already. Do NOT change its required props signature.
You may add optional props. Review the current component first.
Add: optional `subtitle`, optional `actions` slot.

### `<x-stat>` (`components/stat.blade.php`)
Props: `label` (string), `value` (string), optional `icon` (vocabulary key), optional `trend` (+/-/neutral).
Renders a KPI tile with label below value. Used in program brochure pages.

### `<x-field>` (`components/field.blade.php`)
Props: `label`, `name`, `error` (optional), `hint` (optional), `required` (bool).
Wraps a form input slot with label, error, and hint. Always single-column on phone.

### `<x-modal>` (`components/modal.blade.php`)
Alpine-driven. Props: `id` (required), `title`, `size` (sm/md/lg, default md).
Slots: default (body), `footer`.
Closes on Escape. Focus traps inside when open. `aria-modal="true"`.
Respects `--motion-*` for open/close animation.

### `<x-tabs>` (`components/tabs.blade.php`)
Props: `id` (required), `items` (array of {key, label, icon?}).
Deep-linkable via URL `?tab=key`. Restores active tab from URL on load (Alpine).
Accessible: `role="tablist"` + `role="tab"` + `aria-selected`.

### `<x-toolbar>` (`components/toolbar.blade.php`)
Horizontal container for page-level action buttons. Wraps to two lines on phone.
Slots: `start`, `end`.

### `<x-avatar>` (`components/avatar.blade.php`)
Props: `name` (string, initials fallback), `src` (optional URL), `size` (sm/md/lg).
Accessible `alt`.

### `<x-progress>` (`components/progress.blade.php`)
Props: `value` (0–100), `label` (optional), `color` (token name, optional).
`role="progressbar"`, `aria-valuenow`, `aria-valuemin`, `aria-valuemax`.

### `<x-timeline>` (`components/timeline.blade.php`)
Vertical event list. Each item: `timestamp`, `label`, `icon` (vocab key), optional `variant`.
RTL-mirrored (the stem is on the logical-start side).

### `<x-file-drop>` (`components/file-drop.blade.php`)
Alpine drag-and-drop zone. Props: `name`, `accept`, `max-size` (bytes).
Emits the selected file to Alpine state. Works without drag (click to browse).
Error messages localized.

### Mobile-first grid primitives (CSS only, add to spims-theme.css)
Utility classes `.grid-auto-sm`, `.grid-auto-md`, `.grid-auto-lg` using
`repeat(auto-fill, minmax(..., 1fr))` so agents can use auto-fit grids without writing inline CSS.

## Gate (`tests/Feature/Design/ComponentLibraryTest.php` — create it):
- Every component above renders with minimum required props and with full props
- `<x-page-header>`'s EXISTING call signature still works (pass the same props the current 67
  usages pass — confirm by grepping resources/views for x-page-header calls)
- Every component exposes a visible focus state (assert the CSS contains :focus-visible)
- No component emits style="" in its output

Write `echo "A1.4" > .claude/current-step` then `./scripts/validate-step.sh A1.4`.
