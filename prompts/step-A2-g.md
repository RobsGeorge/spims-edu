# Step A2-g — Admin Programs, Courses, Semesters, Users

Step ID: `A2-g`  
Wave: W2b (rebase onto merged A2-a first)  
Scope: resources/views/admin/programs, resources/views/admin/courses,
       resources/views/admin/semesters, resources/views/admin/academic-years,
       resources/views/admin/grading-schemes, resources/views/admin/users,
       resources/views/admin/translations, resources/views/admin/theme

## Scope (17 files)

## What to do
Apply migration table from `docs/design-system.md`:
1. All banned-pattern replacements
2. `<x-badge>` for program status, user role indicators
3. Grading scheme views: numeric columns need `tabular-nums`
4. Mobile-first grid pass — admin forms can be complex, multi-column on desktop → stacked on phone
5. Localize in ALL THREE locales
6. Verify 360/768/1440 × light/dark/RTL

## Note
Step 7 (program rule-builder) will add new fieldsets to admin/programs views. This lane does the
design-system migration only — do not add new fields. Note any UX problems in the PR.

## Done
`echo "A2-g" > .claude/current-step` then `./scripts/validate-step.sh A2-g`
