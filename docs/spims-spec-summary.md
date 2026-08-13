# SPIMS Spec Summary (traces to spims-spec v0.2)

Source of truth for product intent lives in the original design package (`RobsGeorge/Spims`):
`docs/spims-spec-v0.2.md`, `docs/frontend-architecture.md`, `docs/claude-code-build-brief.md`,
`DESIGN.md`, and `design-reference/*`. This file is the condensed contract for the Laravel port
(`spims-edu`).

## Product

Standalone SIS + LMS for Spims (Coptic Orthodox online school). Not multi-tenant.
Success: a student on a phone in Arabic applies, sits a timed exam, and pays an invoice without
doubt; an instructor locks grades without fear; a financial admin reconciles dual-currency money
with no rounding surprises.

## Roles (7)

| Role | Owns |
|---|---|
| Super Admin | Everything; admin-role grants; cross-system audit |
| Administrative Admin | Admissions, forms, semesters, scheduling, enrollment overrides, branding |
| Academic Admin | Programs, courses, templates, offerings, translations, grade reopen |
| Financial Admin | Pricing, invoices, payments, refunds, wallet points, donations |
| Instructor | Offering content, assessments, grading, announcements, discussions, **grade lock** |
| TA | Content, announcements, grading, discussions (no final lock) |
| Student | Apply, enroll, learn, submit, pay, grades, transcript, wallet |

Multi-role users: effective permissions = union.

## Non-negotiables

1. `AuthorizeService` + permission keys — no role-name string checks in controllers.
2. Mutations through services + `AuditLogWriter::withAudit()`.
3. Money = integer minor units + `Currency`. No floats. No currency conversion.
4. UI strings localized (ar RTL-first, en, fr).
5. Additive migrations only.
6. Tests per phase; CI gate before deploy.
7. Mailers/OTP optional in dev — never block build.
8. No npm build step (Blade + CDN CSS/JS).

## Domains (v1)

- **Identity** — OTP auth, multi-role, theme preference, branding
- **Academics** — programs, courses, prerequisites, interest flags, grading schemes, templates, translations
- **Offerings** — years/semesters, cohort vs self-paced, weeks/items, gating, regional pricing
- **Admissions** — dynamic forms, review queue, round-robin, matriculation
- **Enrollment** — windows, waitlist, holds, drop/withdraw refunds, degree audit
- **Finance** — invoices, wallet (4 buckets), PayPal/Paymob/Cashier, split pay, receipts, donations
- **Assessment** — banks, exam runner, autosave, server timer, AI essay suggest, assignments
- **Gradebook** — weighted components, lock/reopen, GPA, academic records
- **Live** — Zoom schedule (1-host aware), attendance import, reminders
- **Discussions** — boards/threads, graded participation, moderation
- **Credentials** — transcript, program/standalone certificates, public QR verify
- **Notifications** — in-app + email (WhatsApp = v2)

## Frontend intent (from design package)

- **Visual:** “Sacred Academic” — cool near-white field (`#f8f9ff`), liturgical burgundy (`#5d0326`),
  academic gold accent; Playfair Display + Inter (Latin); IBM Plex Sans Arabic for Arabic.
  Explicitly **not** warm parchment/cream skeuomorphism.
- **Shell:** role-aware sidebar + sticky topbar; mobile drawer + bottom-nav.
- **Student dashboard:** asymmetric bento (courses + live feature tile + wallet), not uniform hub tiles.
- **Core surfaces:** Course Player, Exam Runner, Teach hub, Catalog, Checkout, Wallet, Degree Audit,
  Theme Editor (tokens + logos), operator DataTables.

## Original phase sequence (backend)

| Phase | Goal |
|---|---|
| A+0 / 0 | Foundation guardrails, schema, CI |
| 1 | Identity, roles, theming |
| 2 | Academics curriculum |
| 3 | Offerings, content, gating, pricing |
| 4 | Admissions + enrollment |
| 5 | Finance |
| 6 | Assessment + gradebook |
| 7 | Live, attendance, discussions, notifications |
| 8 | Credentials + i18n/RTL polish |
| 9 | Hardening, backups, seed, runbooks |

Laravel `spims-edu` completed domain Phases 0–9 plus portal hubs / roles hub. Remaining work is
primarily **design-system fidelity**, **missing product surfaces** (especially Course Player and
Teach hub), and **real integrations**. See [portal-design-gap-analysis.md](./portal-design-gap-analysis.md).
