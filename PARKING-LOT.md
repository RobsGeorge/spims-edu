# Parking lot

Out-of-phase ideas that must not dilute the current design-gap roadmap.
Promote an item into an active phase only when the phase owner accepts it.

## Explicitly v2 (from spec)

- WhatsApp notifications
- Hard exam proctoring (beyond soft integrity)
- Multi Zoom-host concurrent meetings (UI for N licenses)
- Attendance “excused” state (spec uses present/absent only)

## Ideas deferred from gap scan

- Full REST JSON API surface matching original `api-route-structure.md` (current app is Blade-first)
- WebSockets / SSE for notifications & discussions (v1 = poll/reload)
- Framer Motion–class route transitions (keep CSS/Alpine micro-motion under no-npm constraint)
- Public marketing site beyond the in-app landing
- Parent / guardian roles
- Mobile native apps
- AI chat tutor / content generation beyond translation + essay suggest
- Multi-school / multi-tenant (explicitly out of scope)

## Spec docs not yet vendored into this repo

Original design package (`RobsGeorge/Spims`) still holds full `design-reference/*` HTML/PNG screens
and Prisma-era docs. Consider vendoring selected references under `design-reference/` when a design
phase starts — do not bulk-copy the Next.js app.
