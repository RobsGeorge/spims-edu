# Step 3 — Programs-First Catalog

Step ID: `3`  
Wave: W4 (needs Step 1) | New UI — design system native.  
Scope: resources/views/catalog

## What to build
Rebuild the catalog view. **The `$programs` variable is currently passed to the view but never
rendered.** That is the primary bug this step fixes.

### Three tabs via `<x-tabs>`
- **Programs** (default): program cards, price via `<x-money>`, filters for type + mode
- **Courses**: course cards with "Part of: [program chip]" links, price, prerequisites count
- **Standalone**: courses not attached to any program

### Features
- Price on every card via `<x-money>`
- "Part of:" program chips on course cards (linked to `/programs/{code}`)
- Filters: program type, delivery mode — **composable** (multiple filters active at once)
- Deep-linkable: `?tab=courses&type=diploma&mode=online` restores on load
- Empty filter result: `<x-empty-state>`, not a blank page

### Shared partials
Create `resources/views/partials/program-card.blade.php` and `course-card.blade.php`
(used here and in Step 2's featured courses, so they must be generic enough for both).

## Gate
- `$programs` is actually rendered (assert a seeded program name appears in the output)
- Each tab is deep-linkable and restores from the URL (assert ?tab=courses shows the courses tab)
- Filters compose (active type + active mode → filtered result, not OR)
- Empty filter result shows `<x-empty-state>`

`echo "3" > .claude/current-step` then `./scripts/validate-step.sh 3`
