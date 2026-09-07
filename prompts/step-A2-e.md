# Step A2-e — Finance, Credentials, Enrollments, Grades, Advising, Applications

Step ID: `A2-e`  
Wave: W2b (rebase onto merged A2-a first)  
Scope: resources/views/finance, resources/views/credentials, resources/views/enrollments,
       resources/views/grades, resources/views/advising, resources/views/applications

## Scope (17 files)

## What to do
Apply migration table from `docs/design-system.md` to every file:
1. All banned-pattern replacements
2. `<x-money>` for every financial amount — finance views will have many *_minor values
3. `<x-badge>` for status fields (enrollment status, application status, credential status)
4. Mobile-first grid pass — grades tables must use `<x-data-table>`
5. `tabular-nums` on all numeric columns
6. Localize in ALL THREE locales
7. Verify 360/768/1440 × light/dark/RTL

## Special notes
- Finance: EVERY displayed amount must go through `<x-money>` — this is the highest risk for raw
  minor units. Search for `_minor }}` in your scope before finishing.
- Grades: grade tables are wide (>4 columns) — they MUST use `<x-data-table>` for the mobile
  card fallback. A horizontally-scrolling grades table at 360px is not acceptable.

## Done
`echo "A2-e" > .claude/current-step` then `./scripts/validate-step.sh A2-e`
