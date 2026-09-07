# Step A1.2 — Surface Tier System

Step ID: `A1.2`  
Wave: W1 (sequential)  
Scope: resources/views/components  
Prerequisite: A1.1 tokens are committed.

## Goal
Replace "one card for everything" with three documented tiers.

## What to build

### `<x-card>` component (`resources/views/components/card.blade.php`)
Props:
- `variant`: `'panel'` | `'quiet'` | `'bare'` — required, no default (an unknown variant throws)
- `class`: extra classes passthrough
- `tag`: HTML element, default `'div'`

Tier definitions (use tokens from A1.1 + existing `--radius-*`, `--shadow-*`, `--color-*`):
- **panel**: primary surface — `--shadow-lift`, `--radius-lg`, `--color-surface` background,
  `--color-surface-border` border. Use for the main content card on a page.
- **quiet**: secondary surface — `--shadow-soft` or no shadow, `--radius-md`,
  `--color-surface-low` background, subtle or no border. Use for sidebars, secondary cards.
- **bare**: no elevation — no shadow, no border, `--radius-sm` or 0, transparent or `--color-bg-1`.
  Use for lists, table wrappers, inline groupings.

The three tiers must produce **visually distinct** output — a tier that looks identical to another
is a design failure.

### Documentation
Add a section to (or create) `docs/design-system.md`:
```
## Surface Tiers
| Tier   | When to use | Token classes |
| panel  | ...         | ...           |
| quiet  | ...         | ...           |
| bare   | ...         | ...           |
```
Include one Blade example per tier.

## Gate (`tests/Feature/Design/SurfaceTierTest.php` — create it):
- All three variants render without error
- Each variant emits a **different** set of CSS classes (assert class strings differ)
- Passing an unknown variant throws an exception (not a silent fallback)
- Each variant is theme-aware (dark mode token used, not a hardcoded colour)

Write `echo "A1.2" > .claude/current-step` then `./scripts/validate-step.sh A1.2`.
