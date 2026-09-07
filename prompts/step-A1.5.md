# Step A1.5 — `<x-data-table>`

Step ID: `A1.5`  
Wave: W1 (sequential)  
Scope: resources/views/components  
Prerequisite: A1.1–A1.4 committed.

## Goal
The single component the responsive mandate rests on. Every data table in the portal must render
through this component from A2 onward.

## What to build: `<x-data-table>` (`components/data-table.blade.php`)

### Desktop: standard `<table>`
- Wrapped in `.spims-table-wrap` (overflow-x: auto) so it scrolls inside its container, not the page.
- `thead` with `<th scope="col">` for every column.
- Numeric columns: pass `numeric: true` in the column definition → applies `tabular-nums` + `text-align: end`.
- Empty state: renders an `<x-empty-state>` (the existing component) when the row collection is empty.

### Mobile: stacked labelled cards (renders below `md` breakpoint via CSS display, not JS)
- One card per row. Each card shows label: value pairs in a two-column dl or grid.
- Column labels from the thead are repeated as labels in each card.
- Destructive actions get icon + text (never icon-only) in the card too.
- Implemented as a second HTML block (`.d-none .d-md-block` / `.d-block .d-md-none` pattern or
  equivalent using only Bootstrap breakpoint classes — no JS required to switch).

### API
```blade
<x-data-table :columns="[
    ['key' => 'name',   'label' => __('ui.name')],
    ['key' => 'score',  'label' => __('ui.score'), 'numeric' => true],
    ['key' => 'status', 'label' => __('ui.status')],
]" :rows="$items">
    {{-- optional: named slot 'actions' receives $row, renders per-row action buttons --}}
    <x-slot name="actions" :row="$row">
        <a href="{{ route('...', $row) }}" class="btn btn-sm">{{ __('ui.view') }}</a>
    </x-slot>
</x-data-table>
```

### Rules
- A table with more than 4 columns that does NOT use `<x-data-table>` is caught by
  `ResponsiveContractTest` (A3). Design `<x-data-table>` to make that test passable.
- RTL: column order reverses naturally via `dir="auto"` or logical properties. Test this.
- `tabular-nums` applied via a CSS utility class, not inline style.
- Sorting: optional. If a `sortable: true` column prop is passed, render a sort button. The
  component emits a `sort` event via Alpine for the parent to handle — it does not sort itself.

## Gate (`tests/Feature/Design/DataTableTest.php` — create it):
- A single invocation emits both the desktop table markup AND the mobile card markup in one render
- Column labels appear in both the thead and in each mobile card (assert the label string appears twice)
- A table built with more than 4 columns but without `<x-data-table>` would fail (assert the
  component handles 5+ columns correctly, as validation it's capable of replacing them)
- Numeric columns carry the tabular-nums CSS class
- Empty collection renders `<x-empty-state>`, not an empty `<tbody>`
- Renders correctly with RTL (assert the output contains logical-property classes or dir attribute)

Write `echo "A1.5" > .claude/current-step` then `./scripts/validate-step.sh A1.5`.
