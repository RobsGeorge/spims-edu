# Parking lot

Out-of-phase ideas that must not dilute the current design-gap roadmap.
Promote an item into an active phase only when the phase owner accepts it.

## Explicitly v2 (from spec)

- WhatsApp notifications — stays parked. The academic roadmap builds a channel abstraction in S2;
  the WhatsApp driver itself is deliberately left unimplemented.
- Multi Zoom-host concurrent meetings (UI for N licenses)

## Still deferred

- WebSockets / SSE for notifications & discussions, and Laravel Reverb for live quiz — S9 domain
  (events + live quiz) shipped with polling. Reverb is still optional and not required for CI.
- Framer Motion–class route transitions (keep CSS/Alpine micro-motion under no-npm constraint)
- Public marketing site beyond the in-app landing
- Parent / guardian roles — explicitly out of scope; Khedma's guardian check-in path is not being ported
- Mobile native **client apps** — the API is being built (see below); the apps themselves stay out of scope
- AI chat tutor / content generation beyond translation + essay suggest
- Multi-school / multi-tenant (explicitly out of scope)

## Scheduled for promotion — accepted, not yet built

- **Lockdown-browser-class exam enforcement** — remains out of scope. S5 shipped the proctor event
  log, warning escalation, and attempt termination.

## Promoted and delivered

- **Hard exam proctoring (event log + termination)** — **S5**. `proctor_events` plus
  `assessment_attempts.terminated_for_cheating`. Lockdown-browser-class enforcement stays parked.

- **Attendance “excused” state** — **S3**. `attendance_entries` records `PRESENT` / `ABSENT` /
  `LATE` / `EXCUSED` on `class_sessions` independent of Zoom.
- **Full REST JSON API surface** matching the original `api-route-structure.md` (the app remains
  Blade-first for its own UI). **Foundation done in S1** — `/api/v1` with `login`, `logout`, `me`
  and `branding`. S2 added announcements/notifications/settings; S3 added attendance/roster.
  Remaining endpoint surface: **S8** (instructor API). Student waves A–E are on `main`.

## Spec docs not yet vendored into this repo

Original design package (`RobsGeorge/Spims`) still holds full `design-reference/*` HTML/PNG screens
and Prisma-era docs. Consider vendoring selected references under `design-reference/` when a design
phase starts — do not bulk-copy the Next.js app.
