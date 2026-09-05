# Parking lot

Out-of-phase ideas that must not dilute the current design-gap roadmap.
Promote an item into an active phase only when the phase owner accepts it.

## Explicitly v2 (from spec)

- WhatsApp notifications — stays parked. The academic roadmap builds a channel abstraction in S2;
  the WhatsApp driver itself is deliberately left unimplemented.
- Multi Zoom-host concurrent meetings (UI for N licenses)

## Still deferred

- WebSockets / SSE for notifications & discussions (v1 = poll/reload) — scheduled last, in S9, with
  a polling fallback so no earlier phase depends on it
- Framer Motion–class route transitions (keep CSS/Alpine micro-motion under no-npm constraint)
- Public marketing site beyond the in-app landing
- Parent / guardian roles — explicitly out of scope; Khedma's guardian check-in path is not being ported
- Mobile native **client apps** — the API is being built (see below); the apps themselves stay out of scope
- AI chat tutor / content generation beyond translation + essay suggest
- Multi-school / multi-tenant (explicitly out of scope)

## Scheduled for promotion — accepted, not yet built

These have been accepted into the academic roadmap and are **no longer parked**, but none is
implemented yet. See [docs/academic-roadmap/](docs/academic-roadmap/).

- **Attendance “excused” state** — S3. The spec's present/absent-only model cannot report attendance
  credibly for an SIS; S3 makes attendance a first-class record with an excuse reason.
- **Hard exam proctoring** — S5, *partially*: the proctor event log, warning escalation and attempt
  termination are in scope. Lockdown-browser-class enforcement remains out of scope.

## Promoted and delivered

- **Full REST JSON API surface** matching the original `api-route-structure.md` (the app remains
  Blade-first for its own UI). **Foundation done in S1** — `/api/v1` with `login`, `logout`, `me`
  and `branding`, a single error envelope, `Accept-Language` resolution, and an OpenAPI document
  guarded by a coverage test. The endpoint surfaces themselves remain outstanding: **S6** (student
  API, waves A–E) and **S8** (instructor API).

## Spec docs not yet vendored into this repo

Original design package (`RobsGeorge/Spims`) still holds full `design-reference/*` HTML/PNG screens
and Prisma-era docs. Consider vendoring selected references under `design-reference/` when a design
phase starts — do not bulk-copy the Next.js app.
