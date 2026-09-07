# Step A2-f — Admin Offerings, Gradebook, Assessments, Attendance

Step ID: `A2-f`  
Wave: W2b (rebase onto merged A2-a first)  
Scope: resources/views/admin/offerings, resources/views/admin/gradebook,
       resources/views/admin/assessments, resources/views/admin/assessment-templates,
       resources/views/admin/attendance, resources/views/admin/completion-criteria,
       resources/views/admin/offering-closing

## Scope (16 files)

## What to do
Apply migration table from `docs/design-system.md` to every file in scope:
1. All banned-pattern replacements
2. Admin gradebook: wide tables → `<x-data-table>` with mobile card fallback
3. `<x-badge>` for assessment status, attendance status, completion status
4. `<x-money>` where financial amounts appear
5. Mobile-first grid pass
6. Localize in ALL THREE locales
7. Verify 360/768/1440 × light/dark/RTL

## Done
`echo "A2-f" > .claude/current-step` then `./scripts/validate-step.sh A2-f`
