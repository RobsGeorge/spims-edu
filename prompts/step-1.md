# Step 1 — Public Program Pages

Step ID: `1`  
Wave: W3 | New UI — use the design system natively. No legacy patterns.  
Scope: routes, database/migrations, resources/views/programs  

## What to build

### Additive migration
Add nullable columns to existing tables (additive only):
- `programs.description` (text, nullable)
- `programs.marketing_summary` (text, nullable)
- `courses.description` (text, nullable)

### Routes
Append at `// --- TRACK: public-marketing ---` in routes/web.php:
- `GET /programs` → `ProgramCatalogController::index` (guest-accessible)
- `GET /programs/{code}` → `ProgramCatalogController::show` (guest-accessible)

### Views: `/programs` (program listing)
Program cards with: name, type, level, total credits, fee summary via `<x-money>`, apply CTA.
Hide programs with no published courses.

### Views: `/programs/{code}` (program brochure)
- Hero: program name, type, marketing_summary
- `<x-stat>` row: total credits, elective credits, max semesters (`max_semesters_to_graduate`),
  passing threshold (`passing_threshold`)
- Per-`year_level` course sequence: Year 1 → Year 2 etc., required vs elective
- Fee summary via `<x-money>` (registration fee, semester fee)
- Apply CTA: links to the application form if `Route::has('applications.create')`; if not,
  falls back to a "Contact admissions" email/phone. **Never a dead link.**
- Print stylesheet: the brochure prints cleanly at A4

### Quality floor
All GLOBAL CONTRACT requirements. Guest-accessible (no auth required).
Arabic: program names and descriptions are typically in Arabic — test RTL at 360px.

## Gate
- `/programs` returns 200 for a guest
- `/programs/{code}` returns 200 for a guest with a seeded program
- A program with no open offering still renders with a working apply/contact CTA (no dead link)
- No raw minor units in any rendered output
- All copy resolves in ar, en, fr

`echo "1" > .claude/current-step` then `./scripts/validate-step.sh 1`
