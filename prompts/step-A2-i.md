# Step A2-i — Admin Reports, Finance, Live, Superadmin

Step ID: `A2-i`  
Wave: W2b (rebase onto merged A2-a first)  
Scope: resources/views/admin/reports, resources/views/admin/finance,
       resources/views/admin/live, resources/views/superadmin

## Scope (16 files — includes superadmin/audit/)

## What to do
Apply migration table from `docs/design-system.md`:
1. All banned-pattern replacements
2. Finance reports: ALL amounts through `<x-money>` — search `_minor }}` before finishing
3. Report tables: wide tables → `<x-data-table>` with mobile card fallback
4. Superadmin views: keep the visual distinction between superadmin and regular admin clear —
   use the `panel` tier for superadmin actions (they are high-impact and should feel serious)
5. Mobile-first grid pass
6. Localize in ALL THREE locales
7. Verify 360/768/1440 × light/dark/RTL

## Done
`echo "A2-i" > .claude/current-step` then `./scripts/validate-step.sh A2-i`
