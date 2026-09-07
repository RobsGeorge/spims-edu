# Step 0 — QA Harness (smoke test baseline)

Step ID: `0`  
Wave: W0  
Scope: tests/Feature/Smoke  
**No production code in this step.**

## Goal
Create `tests/Feature/Smoke/PublicSurfacesSmokeTest.php` that enumerates routes and asserts each
returns a non-5xx response for an appropriately-permissioned actor.

## What to build

1. **`PublicSurfacesSmokeTest`** — iterates over routes in `routes/web.php` and hits each one:
   - Guest routes: anonymous request, assert 200 or 302 (not 5xx)
   - Authenticated routes: use a seeded `student1@spims.test` actor, assert 200 or 302 or 403 (not 5xx)
   - Admin routes: use a seeded admin actor
   - Any 5xx = failing test

2. **Written gap list** — commit to `docs/smoke-baseline.md`: a markdown table of every route that
   returns 404 or 500 with the anonymous or minimum-permission actor. This is the baseline the rest
   of the pack works against. It must exist even if empty.

## Acceptance criteria (gate)
- `PublicSurfacesSmokeTest` exists and passes
- `docs/smoke-baseline.md` exists and is committed
- Zero routes return 5xx (any 5xx is a test failure, not a skip)
- Write your step ID to `.claude/current-step` before declaring done: `echo "0" > .claude/current-step`
