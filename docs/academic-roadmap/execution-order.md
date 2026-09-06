# Execution order

The order to build the plan in, and how each step is proved. Companion to
[`implementation-plan.md`](implementation-plan.md), which holds the detail; this file is the
sequence and the exit criteria.

**Status:** S0–S3 are complete and verified — see [`README.md`](README.md). S4 and S5 are next.

---

## The rule that sets the order

A phase may start only when the thing it authorizes, notifies through, or reads from already exists.
That produces one hard prerequisite chain and a lot of parallelism after it:

```
S0 authorization scope  ✅ done
      │
      ├── S1 API foundation ──┬── S6 student API (per wave)
      │                       └── S8 instructor API
      ├── S2 communications spine ── S5 assessment completion
      ├── S3 attendance + roster ── S4 completion + credentials
      └── S7 team projects

S9 realtime (live quiz, events) — needs S1 + S2, otherwise independent
```

S0 and S1 are the only true bottlenecks. Once S1 lands, S2/S3/S7 can proceed in parallel by
different agents without colliding: they touch disjoint tables and services.

---

## Order

| # | Phase | Why here | Done when |
|---|---|---|---|
| 0 | **S0 — resource scope** ✅ | Every later phase adds routes; adding them over unenforced `O` multiplies a live vulnerability | `ResourceScopeTest` green; cross-offering denied on every route |
| 1 | **S1 — API foundation** ✅ | Fixes the envelope, errors, and locale once, before 60+ endpoints bake in a shape | Token auth works; `/me` returns Arabic; OpenAPI coverage test green |
| 2 | **S2 — communications spine** | Announcements, reminders, graduation notices and project deadlines all need one delivery path and one log. Per-feature notification code is how delivery reporting becomes impossible | Instructor publishes to an offering; every enrolled student receives it in their locale; the registrar exports a CSV proving it |
| 3 | **S3 — attendance + roster** | The largest SIS gap, and S4's criteria engine reads from it | An in-person session with no Zoom meeting is marked, excused, and reported; the gradebook attendance component is unchanged from outside |
| 4 | **S5 — assessment completion** | Small, additive, unblocks the S6 Wave C write path | Proctor events escalate and terminate; offline assignments; reminders fire once |
| 5 | **S4 — completion + credentials** | Needs S3 (attendance criteria) and S2 (announce) | Criteria evaluated, grace marks applied, offering closed, PDF certificate verifies publicly |
| 6 | **S6 — student API, waves A→E** | Each wave ships only after its domain phase | A student completes the year on a phone in Arabic |
| 7 | **S7 — team projects** | Largest new subsystem, but self-contained — can run in parallel from step 2 onward | Team formation, deliverables, grading, peer evaluation, all scoped |
| 8 | **S8 — instructor API** | Needs S0 + S3 + S5 + S7 to have anything to expose | Instructor runs an offering from a phone; TA is refused the lock |
| 9 | **S9 — realtime, live quiz, events** | Introduces Reverb; last so nothing else depends on new infrastructure | Live quiz runs with Reverb and degrades to polling when it is off |

**If you can only do three things:** S1, S2, S3. They unblock the mobile API, make notifications
reportable, and turn attendance into an actual academic record — the three things standing between
this app and "full SIS".

---

## How each phase is proved

The same loop that produced the three delivered patches, and the reason they can be trusted:

1. **Write the test first and watch it fail** against the code as it ships. A regression test that
   was never red proves nothing. Each delivered commit records its pre-fix failing output in its
   own commit message.
2. **Implement the smallest change** that turns it green.
3. **Run the full suite.** A phase is not done because its own tests pass; it is done when it has
   not broken anything else.
4. **Run `pint --test` on the changed files only.** The repository has pre-existing style deltas;
   judge new code, do not reformat files the change does not own.
5. **Run the PostgreSQL gate** whenever a migration is involved: `migrate:fresh --seed` against
   PostgreSQL 16, plus a rollback and re-apply. Tests use in-memory SQLite, so a Postgres-only
   problem will not otherwise surface until deploy.

### Per-phase test additions

| Phase | Suite | The test that matters most |
|---|---|---|
| S0 ✅ | `Auth` | `ResourceScopeTest` — cross-offering denied on every route |
| S1 | `Api` | `OpenApiCoverageTest` — every registered route is documented |
| S2 | `Communications` (new) | `CommunicationLogTest` — every dispatch is logged and exportable |
| S3 | `Attendance` (new) | `AttendanceConcurrencyTest` — two instructors marking one roster; the stale write gets 409 and loses nothing |
| S4 | `Completion` (new) | `CertificateIssuanceTest` — closing issues exactly one credential per completing student, idempotently |
| S5 | `Assessment` | `ProctorEscalationTest` — a terminated attempt cannot be resumed or submitted |
| S6 | `Api` | `StudentApiScopeTest` — no student reads another student's records by guessing a ULID |
| S7 | `Projects` (new) | `PeerEvaluationDoesNotGradeTest` — peer ratings leave `project_member_grades` untouched |
| S8 | `Api` | `InstructorApiScopeTest` — parameterized over the route list, so a new route without a scope test fails the build |
| S9 | `LiveQuiz`, `Events` (new) | `LiveQuizFallbackTest` — with broadcasting off, polling returns the same state |

New suites must be added to both `phpunit.xml` and the gate in `.github/workflows/ci.yml`. Put
`Auth` early in the gate so a scope regression stops the run before anything else.

### The four invariants

These are not phase tests; they must be green on **every** PR from now on.

1. **Scope** — `ResourceScopeTest` plus each phase's `*ScopeTest`. Cross-offering access fails.
2. **API parity** — the API and the web surface share services, so their outputs must agree. A
   divergence means logic leaked into a controller.
3. **Money** — no float reaches a money column; every API money payload is the three-field object.
4. **Audit** — every service mutation writes an audit row, parameterized over the service method
   list so a new unaudited mutation fails the build.

---

## Fixture note carried forward from S0

Holding the Instructor or TA role no longer implies authority over an offering. Any fixture that
acts on an offering must staff the actor explicitly:

```php
$this->staffOffering($instructor, $offering);
```

The helper lives on `Tests\TestCase`. S0 updated six existing fixtures that had been passing only
because scoping did not exist; new tests should assume the strict behaviour from the start.

---

## What S0 changed that later phases must respect

- `config/permission_scopes.php` lists the 14 offering-scoped permission keys. **A new
  offering-owned permission key must be added there**, or it will be enforced at role level only.
- `ResourceScopeResolver::offeringIdsFor()` resolves resources explicitly per model. **A new
  offering-owned model must be added there**, or it resolves to no offering and is treated as out of
  scope — which fails safe, but will look like a mystery 403.
- `RequirePermission` hands the authorizer the first route-bound model it can resolve, so
  `/admin/*` routes need no per-route changes.
- Scoped actions **fail closed**: calling one without a resource throws. If a new service method
  authorizes a scoped key, it must pass the resource.
