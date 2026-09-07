# Portal + demo agent run — 2026-09-07

Base URL: `http://127.0.0.1:8000`  
DB: sqlite (`database/database.sqlite`) after `migrate:fresh --seed`  
Branch: `cursor/demo-console-bcff` (merged `cursor/portal-test-fixes-bcff`)  
`DEMO_CONSOLE=true`, `SEED_DEMO_DATA=true`

## Summary

| Wave | Result | Notes |
|---|---|---|
| 0 Automated | **PASS** | 15 + 5 + 79 + 33 PHPUnit tests green (see below) |
| 1 Demo console | **PASS** | CTA, tiles, RTL/FR, Enter John/Mina, 404 unknown persona |
| 2 Guest | **PASS** | Home, catalog, preview, auth, health, branding |
| 3 Student | **PASS** | Learn TH101, dashboard, finance, attendance, hubs. S3 `/projects/mine` was a **plan typo** — real route is `/projects` (200) |
| 4 Teach | **PASS** | All TH101 tabs + dossier + TA. T18 200 on `/admin/programs` is **by design** (`programs.view` R) |
| 5 Staff hubs | **PASS** after re-check | Communications URL is `/admin/communications`. Admin may **view** programs + finance. Finance **403** on applications |
| 6 Persona smoke | **PASS** after fix | First run **429** after ~10 Enters. **Fixed**: `demo-enter` 10 → **60**/min. Re-run: **17/17** 302 |
| 7 Chrome / 403 | **PASS** after re-check | Walker followed 302→login and called it 200. `actingAs` proves John 403 on users/superadmin; Mina 403 on admin finance |
| 8 Super Admin | **SKIPPED** | Local-only; not required for the school trial |
| M Mutating | **PASS** (seeded surfaces) | Seed, Enter John, start TH101 quiz (runner 200), Reset. Survey/event/project **N/A** — not in this branch’s `DemoDataSeeder` |

**Product defect found and fixed:** public `/demo/enter/{persona}` throttled at 10/min per IP, so a walk through the 17 tiles returned HTTP 429. Raised to 60/min. Regression: `a_full_persona_walk_is_not_throttled`.

**Not product defects:** wrong paths in the first plan (`/projects/mine`, `/admin/communications/report`); expected 403s that the permission matrix grants as R (`programs.view`, `finance.invoices` for office admin).

Password `Spims@Test2026!` never appeared in HTML. Super Admin never listed on `/demo`.

## Wave 0 PHPUnit

| Suite | Result |
|---|---|
| `DemoConsoleTest` + `HomePageTest` + `DemoDataSeederTest` + `LoginFlowTest` | OK 15 tests, 98 assertions (pre-fix) |
| `PortalAuthzWalkTest` | OK 5 tests, 15 assertions |
| `--testsuite Portal,StaffUi` | OK 79 tests, 521 assertions |
| Student survey/event/live-quiz/project/completion UI | OK 33 tests, 309 assertions |
| Demo tests after rate-limit fix | OK 13 tests, 99 assertions |

## Wave 6 re-run (after merge)

All `POST /demo/enter/{slug}` → 302:

| Slug | Location |
|---|---|
| student1 | `/learn/{TH101}` |
| student6 | `/dashboard` |
| ins1 | `/teach/{TH101}` |
| aca | `/admin/programs` |
| adm | `/admin/applications` |
| fin | `/admin/finance` |
| ta1, dual, ins2 | `/teach` |
| student2–5, 7–10 | `/dashboard` |

## Wave M

- Seed confirm → 302 `/demo`
- Enter John → 302 `/learn/{TH101}` 200, no password
- `POST /assessments/{TH101 Week 1 check}/start` → runner 200
- Reset confirm → 302 `/demo`, John tile still present, no password

## Authz (`actingAs`, seeded)

| Actor | URL | Status |
|---|---|---|
| student1 | `/admin/users`, `/superadmin`, `/admin/finance` | 403 |
| student1 | `/projects` | 200 |
| ins1 | `/admin/programs` | 200 |
| ins1 | `/admin/finance`, `/admin/users` | 403 |
| adm | `/admin/applications`, `/admin/programs`, `/admin/finance` | 200 |
| adm | `/admin/programs/create` | 403 |
| fin | `/admin/finance` | 200 |
| fin | `/admin/applications` | 403 |
| aca | `/admin/communications` | 200 |
| aca | `/admin/communications/report` | 404 |

## Artifacts

- This file: `docs/test-runs/2026-09-07-portal-agent-run.md`
- Raw first HTTP pass: `docs/test-runs/wave-http-run.md` (includes false 429/403 rows)
- Screenshots from the demo console build remain under `/opt/cursor/artifacts/demo_*.png`
