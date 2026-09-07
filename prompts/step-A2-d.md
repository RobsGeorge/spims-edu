# Step A2-d — Teach & Offerings

Step ID: `A2-d`  
Wave: W2b (rebase onto merged A2-a first)  
Scope: resources/views/teach, resources/views/offerings

## Scope (20 files — the largest lane)
All subdirs under `teach/`: projects, live-quiz, attendance, live, discussions, assessments,
assignments, completion, students, partials.
Plus `offerings/` (+ partials).

## What to do
Apply migration table from `docs/design-system.md` to every file:
1. All banned-pattern replacements
2. Mobile-first grid pass — many teach views have wide grids, needs care
3. Icons from vocabulary (instructor actions)
4. Localize in ALL THREE locales
5. Verify 360/768/1440 × light/dark/RTL

## Special notes
- Offering week-content builder (`offerings/partials/week-content-builder.blade.php`) is a
  complex form — multi-input rows become a modal or stacked fieldset on phone
- Instructor views still need a usable phone layout (instructors access from tablets and phones)
- Do NOT change logic or data — note any controller data gaps in the PR

## Done
`echo "A2-d" > .claude/current-step` then `./scripts/validate-step.sh A2-d`
