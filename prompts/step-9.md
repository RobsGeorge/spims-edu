# Step 9 — Student Web UI for Events (audit → complete → migrate)

Step ID: `9`  
Wave: W3 | Design system native for new code; migrate existing views.

## IMPORTANT: Existing UI
Events already has student routes and views:
- `GET /events` → `StudentEventController::index` (view: events/index.blade.php, 45 lines)
- `GET /events/mine` → `StudentEventController::mine` (view: events/mine.blade.php, 49 lines)
- `GET /events/{event}` → `StudentEventController::show` (view: events/show.blade.php, 58 lines)
- `POST /events/{event}/reserve`, `POST /events/{event}/cancel`

## Substep 9.1 — Audit
Read the existing views alongside the 7 API endpoints for events.
Produce a written gap list in the PR: for each API capability, does the web UI cover it?
What's missing?

## Substep 9.2 — Complete
Build only the gaps the audit identifies. Expected gaps:
- Capacity meter (visual seats-available indicator)
- Seats-left count (updated dynamically or server-side)
- Reserved state (shows "You're registered" + a check-in code when reserved)
- Check-in code display (QR or alphanumeric code visible once reserved)
- Cancel confirmation (the cancel action must confirm before executing)

## Substep 9.3 — Migrate
Apply the design system migration to all events/ views:
- Replace banned patterns per the migration table
- Mobile-first grid pass
- `<x-badge>` for event status
- Localize in ar, en, fr
- Verify 360/768/1440 × light/dark/RTL

## Gate
- Every API endpoint is either covered by the web UI or explicitly deferred with a written reason
- Reserving a full event fails gracefully (error message, not a 500)
- A non-reserved student cannot see a check-in code
- Capacity meter is correct at 0 seats, 50% seats, 100% seats (full)
- All new copy in ar, en, fr

`echo "9" > .claude/current-step` then `./scripts/validate-step.sh 9`
