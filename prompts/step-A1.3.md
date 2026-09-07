# Step A1.3 — Correctness Primitives

Step ID: `A1.3`  
Wave: W1 (sequential)  
Scope: resources/views/components  
Prerequisite: A1.1 + A1.2 committed.

## Goal
Three components that make whole classes of bug structurally impossible. Build these before any
other component — A2 and every later step depend on them.

## What to build

### `<x-badge>` (`resources/views/components/badge.blade.php`)
Purpose: raw enum output in the UI becomes structurally impossible.

Props:
- `value`: a backed enum instance — required
- `variant`: `'default'` | `'success'` | `'warning'` | `'danger'` | `'info'` — optional,
  inferred from the enum if it implements a `badgeVariant()` method, defaults to `'default'`

Behaviour:
- Reads the lang key automatically: `__(snake_case(class_basename($value::class)) . '.' . $value->value)`
  — the caller never passes a string. Passing a raw string throws.
- Renders as a `<span>` with the appropriate semantic colour token.
- RTL-safe: no direction-specific padding.

### `<x-money>` (`resources/views/components/money.blade.php`)
Purpose: raw integer minor units in the UI becomes structurally impossible.

Props:
- `minor`: integer (minor units) — required
- `currency`: `App\Enums\Currency` enum instance — required
- `class`: passthrough

Behaviour:
- Calls `\App\Support\Money::fromMinor($minor, $currency)->format()` internally.
- The caller never passes a pre-formatted string. Passing a string throws.
- Uses `tabular-nums` font feature so amounts align in tables.
- RTL-safe: the currency symbol position follows the locale.
- Renders as a `<span>`.

### `<x-icon>` (`resources/views/components/icon.blade.php`)
Purpose: single entry point for Bootstrap Icons, keyed by domain concept name.

Props:
- `name`: string key from the icon vocabulary (defined in docs/design-system.md in A1.6) — required
- `size`: `'sm'` | `'md'` | `'lg'` — default `'md'`
- `class`: passthrough

Behaviour:
- Resolves `name` through an internal map array (vocabulary → bi-* class).
- Unknown name throws a descriptive exception rather than silently rendering nothing.
- Renders as `<i class="bi bi-... ...">` with appropriate size class and `aria-hidden="true"`.
- Never takes a raw `bi-*` class as input.

Note: the vocabulary map will be sparse at A1.3 (built out in A1.6). Seed it with ~10 concepts
from the most common existing icons in the repo so the component is immediately usable.

## Gate (`tests/Feature/Design/CorrectnessPrimitiveTest.php` — create it):
- `<x-badge>` given an enum emits the localized label in all three locales (en, ar, fr)
- `<x-badge>` never emits the raw ->value string
- `<x-money>` given (123456, Currency::USD) emits a formatted money string, never bare "123456"
- `<x-money>` renders correctly under RTL (does not assert a specific string, only that it renders)
- `<x-icon>` rejects an unknown vocabulary key with a thrown exception

Write `echo "A1.3" > .claude/current-step` then `./scripts/validate-step.sh A1.3`.
