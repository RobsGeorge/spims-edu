# Academic roadmap

The plan for taking this app from "backend domain phases 0–9 complete" to a full SIS + LMS with a
mobile API, phased S0 → S9. Four documents that cross-reference each other heavily, which is why
they live in a directory rather than loose in `docs/`.

Read them in this order:

| Document | What it is |
|---|---|
| [`gap-analysis.md`](gap-analysis.md) | The evidence. What this app has, what the Khedma academic hub has, and the 21-entry gap register (G-01 … G-21) every phase traces back to. Written against `main` @ `d764d1e`, before S0 and S1 landed, and kept as the baseline record. |
| [`implementation-plan.md`](implementation-plan.md) | The work. Schema, services, permissions, tests and acceptance criteria per phase, plus the corrections found while building S0 and S1. |
| [`execution-order.md`](execution-order.md) | The sequence. Which phase may start when, how each is proved, and the four invariants that must stay green on every PR from now on. |
| [`mobile-api-spec.md`](mobile-api-spec.md) | The wire contract for `/api/v1`, so the mobile client can be written against a fixed shape before every endpoint exists. |

## Status

**S0–S8 are done, plus S6 Wave E, thin Blade staff UIs, and the S9 domain (events + live quiz on polling).** Laravel Reverb remains optional — live quiz degrades to poll.

Landed on branch
[`feat/authz-scope-and-api-foundation`](https://github.com/RobsGeorge/spims-edu/compare/main...feat/authz-scope-and-api-foundation):

| Commit | Phase | What |
|---|---|---|
| [`cdcc89b`](https://github.com/RobsGeorge/spims-edu/commit/cdcc89b) | G-12 defect | `NotificationService` never read `users.notify_email`, so the Settings toggle was inert |
| [`1b02276`](https://github.com/RobsGeorge/spims-edu/commit/1b02276) | G-17 defect | `AssignmentService::submit()` overwrote the previous submission in place and left the old grade attached to unseen content |
| [`a3019f9`](https://github.com/RobsGeorge/spims-edu/commit/a3019f9) | **S0** | `AuthorizeService` accepted a `$resource` and never read it, so any Instructor could lock grades for any offering in the school |
| [`40bb283`](https://github.com/RobsGeorge/spims-edu/commit/40bb283) | **S1** | `/api/v1` foundation: `login`, `logout`, `me`, `branding`, one error envelope, `Accept-Language`, OpenAPI coverage test |
| [`27400aa`](https://github.com/RobsGeorge/spims-edu/commit/27400aa) | **S1** | `login` returned a 500 on PostgreSQL: `personal_access_tokens.tokenable_id` was a bigint against a ULID `users.id` |

The full suite went from 124 to 178 passing on S0/S1, to **212** after S2 and S3, then to **255+** after S4, S5, the discussion-board audit fix, and the Postgres CI job.

| Branch | Phase | What |
|---|---|---|
| [`cursor/s2-communications-spine-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/s2-communications-spine-bcff) | **S2** | Communications spine: publish/targeting/delivery, email templates, per-event preferences, delivery log. Closes G-09…G-12. WhatsApp channel registered, driver not implemented. |
| [`cursor/s3-attendance-roster-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/s3-attendance-roster-bcff) | **S3** | Attendance as an SIS record: class sessions independent of Zoom, excuses, self check-in, roster export, `users.date_of_birth`. Closes G-03, G-04, G-15, G-21. |
| [`cursor/s4-completion-credentials-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/s4-completion-credentials-bcff) | **S4** | Completion criteria, offering closing, certificate templates, real PDF credentials/receipts, staff notes, module assessments. Closes G-13, G-14, G-16. |
| [`cursor/s5-assessment-completion-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/s5-assessment-completion-bcff) | **S5** | Assignment dashboard, reminders, offline delivery, resubmission deadline, proctor escalation, results announcement. Closes G-17, G-18. |
| [`cursor/ci-postgres-full-suite-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/ci-postgres-full-suite-bcff) | CI | Full PHPUnit suite on PostgreSQL 16 (not just migrate:fresh). |
| [`cursor/fix-discussion-board-audit-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/fix-discussion-board-audit-bcff) | G-07 | `DiscussionService::ensureBoard()` no longer writes on GET; provisioning is audited. |
| [`cursor/s4-completion-api-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/s4-completion-api-bcff) | **S4 API** | Student credentials + own completion; staff cohort/evaluate, notes, week assessment. |
| [`cursor/s6-student-api-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/s6-student-api-bcff) | **S6 A–D** | Student mobile API waves A–D. |
| [`cursor/s6e-surveys-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/s6e-surveys-bcff) | **S6E** | Feedback surveys: anonymous submit, sealed identity, Super Admin reveal. |
| [`cursor/s6e-events-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/s6e-events-bcff) | **S6E / S9** | Events: capacity, waitlist, eligibility, signed QR check-in. |
| [`cursor/s6e-livequiz-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/s6e-livequiz-bcff) | **S6E / S9** | Live quiz play + host machine; polling fallback, no Reverb. |
| [`cursor/s6e-projects-bcff`](https://github.com/RobsGeorge/spims-edu/tree/cursor/s6e-projects-bcff) | **S7 / S6E** | Team projects: join/leave, deliverables, peer eval (never grades), announce → gradebook. |

**S8 — instructor mobile API and thin Blade staff UIs** landed on `main`: teaching context and confirmation tokens; gradebook/assignments/assessments; live, projects, discussions, and content; plus survey, event, project, and live-quiz staff pages. S8 hardening adds stable confirmation tokens, `Idempotency-Key` on teach writes, ETags on safe list GETs, and TA denials for lock/announce. Detail in
[`implementation-plan.md`](implementation-plan.md), sequencing in
[`execution-order.md`](execution-order.md).

Still parked: Reverb (polling already works), WhatsApp driver, lockdown browser, native apps, and `gradebook.reopen` on the instructor API (Academic Admin, web only).

## Two things S0 changed for every phase after it

1. A new **offering-owned permission key** must be registered in `config/permission_scopes.php`, or
   it is enforced at role level only — the exact bug S0 fixed.
2. A new **offering-owned model** must be registered in `ResourceScopeResolver::offeringIdsFor()`,
   or it resolves to no offering and is treated as out of scope. That fails safe, but presents as an
   unexplained 403.

Scoped actions also fail closed: authorizing a scoped key without passing the resource throws.

## Not vendored here

The sibling repository this plan was drafted in also holds `access-and-setup.md`, the `.patch`
files, `verify-gap-claims.sh`, and an `evidence/` log directory. None are carried over:
`access-and-setup.md` concerns agent access to that repository, the patches are redundant once
merged into git history, `verify-gap-claims.sh` asserts the **pre**-S0/S1 conditions and would now
report failures for code that is correct, and the evidence logs reference absolute paths from
another machine. The pre-fix failing output each patch recorded lives in its commit message instead.

## Related

- [`../spims-spec-summary.md`](../spims-spec-summary.md) — the product contract this plan extends
- [`../portal-design-gap-analysis.md`](../portal-design-gap-analysis.md) — the design/UX roadmap (phases D0–D6, I1–I2), which runs alongside this one
- [`../../PARKING-LOT.md`](../../PARKING-LOT.md) — what stays deferred, and what this roadmap promotes out
- `docs/api/openapi.yaml` — the machine-checkable `/api/v1` contract, guarded by `OpenApiCoverageTest` (added by S1)
