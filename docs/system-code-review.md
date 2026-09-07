# SPIMS system code review

> **2026-09-07 (`main` @ `acb20cb`):** S0–S8, S6 Wave E, and the S9 domain (events + live quiz on polling) are already on `main`. Prompts #10 (S5), #11 (S4), #14 (S6 A–D), #18 (S7), #19 (S8), and #20 (S9) in [agent-implementation-prompts.md](agent-implementation-prompts.md) are **cancelled — do not re-implement**. `gradebook.reopen` on the instructor API and applicant `WITHDRAWN` already landed (`e50faa8` and after). Leftover work: discussion attachments via `ObjectStorageService`, advisor what-if on the student API, program-level standing overrides, Paymob `integration_id` in `config/services.php`, `permissions:sync` for newer keys, optional Reverb, WhatsApp driver, and items in [PARKING-LOT.md](../PARKING-LOT.md). Sections below are a 2026-09-06 snapshot of `7cec603` unless this banner or §8 says otherwise.

**Date:** 2026-09-06 (snapshot)
**Reviewed:** `main` @ `7cec603` (then: S0–S3 delivered). **Current `main` @ `acb20cb`:** S0–S8 + S6E + S9 domain shipped; S4–S9 are not greenfield.
**Method:** static review of services, schema, routes, views, and tests; live `migrate:fresh --seed`; full PHPUnit gate; HTTP path crawl of every seeded role against the local server.

This document is the engineering review. For school leadership, start with [client-system-overview.md](client-system-overview.md). For demo logins, see [demo-accounts.md](demo-accounts.md). For copy-paste agent prompts to implement the gaps, see [agent-implementation-prompts.md](agent-implementation-prompts.md).

---

## 1. Verdict

SPIMS is a **real SIS + LMS**, not a prototype. The student lifecycle — apply → matriculate → enroll (with waitlist, holds, drop/withdraw) → learn → assess → lock grades → academic records / GPA / transcript → public credential verify — is implemented in services, covered by **212 passing tests (883 assertions)**, and reachable on **234 registered routes**.

It is **not yet a Populi-class product**. At the 2026-09-06 snapshot the domain layer was ahead of the operator UI and the demo seed was a skeleton. **As of `acb20cb`:** S4–S8, S6 Wave E, and the S9 domain are on `main` (Reverb still optional). Matching Populi now means leftover polish and integrations — not rebuilding those phases. See the banner and §8.

| Layer | Maturity |
|---|---|
| Authorization (permission keys + offering scope) | **Grown** (S0) |
| Attendance as an SIS record | **Grown** (S3) |
| Communications spine (announce / log / prefs) | **Grown** (S2) — WhatsApp driver stubbed |
| Enrollment engine | **Grown in service**, thin admin UI |
| Assessment engine | **Grown in service**, incomplete runner UI |
| Finance ledger (integer minor units) | **Grown in service**, mock gateways |
| Course player / teach workspace | **Partial** — two players, one live 500 |
| Credentials / graduation | **Partial** — issue + verify; no PDF, no close-offering ceremony |
| Mobile `/api/v1` | **Grown** (S6 student + S8 instructor + S9 events/quiz on polling; Reverb optional). Snapshot below still describes `7cec603`. |
| Demo data for clients | **Partial** — roles and catalog exist; classrooms are empty |

---

## 2. What was executed (evidence)

| Check | Result |
|---|---|
| `php artisan migrate:fresh --seed` (SQLite) | Green. 18 users, 5 programs, 13 courses, 14 offerings, 12 enrollments, 10 applications. |
| `php artisan test --compact` | **212 passed, 883 assertions**, ~10.3s |
| `GET /health` | `{"status":"ok","checks":{"app":true,"database":true,"cache":true}}` |
| Login as all 8 demo personas | All succeeded |
| Authenticated GET of ~90 role-appropriate paths | 200 on allowed pages; **403 on out-of-role pages** (RBAC holds) |
| `POST /api/v1/login` as `student1@spims.test` | 200, `{ data: { token, token_type, user } }` |
| `GET /learn/{TH101}` and `/courses/{TH101}` as student | 200 (empty weeks — no content items seeded) |
| `GET /teach/{TH101}` as `ins1@spims.test` | **500** — see §6 |

Per-suite counts (re-run individually after the full gate):

| Suite | Tests | Assertions |
|---|---|---|
| Unit | 7 | 20 |
| Database | 2 | 10 |
| Auth | 17 | 55 |
| Audit | 2 | 5 |
| Smoke | 4 | 8 |
| Admin | 4 | 8 |
| Api | 47 | 155 |
| Academics | 9 | 34 |
| Offerings | 17 | 67 |
| Admissions | 2 | 11 |
| Enrollment | 4 | 20 |
| Finance | 6 | 38 |
| Assessment | 7 | 49 |
| Live | 6 | 27 |
| Attendance | 13 | 52 |
| Communications | 14 | 69 |
| Credentials | 3 | 23 |
| Hardening | 5 | 17 |
| Portal | 31 | 162 |
| Rbac | 3 | 8 |
| Integrations | 4 | 16 |

CI (`.github/workflows/ci.yml`) runs those suites in that order, then a parallel PostgreSQL `migrate:fresh --seed`.

---

## 3. Feature maturity

Legend: **Grown** = service + UI + tests, usable with a client today. **Partial** = real logic exists but UI, completeness, or data is missing. **Stub** = schema / flag / planned only.

### 3.1 Fully grown (show these)

**Identity, RBAC, audit**

- Email + password, OTP verify, forgot-password. Seven roles. Super-admin bypass.
- `AuthorizeService` + `config/permissions.php` + offering scope (`permission_scopes.php`, `ResourceScopeResolver`). Instructors cannot act on another offering.
- `AuditLogWriter::withAudit()` on destructive mutations. Super-admin audit page.
- Security headers, login throttle (5/min), auth throttle (10/min), webhook throttle.
- Tri-locale UI (`ar` RTL, `en`, `fr`) and theme editor.

**Admissions happy path**

- Form builder (text, textarea, number, date, select, file, checkbox).
- Draft → submit → round-robin reviewer → accept / waitlist / reject → matriculation creates `student_programs`.
- Applicant and admin UIs exist. Tested in `AdmissionsFlowTest`.

**Enrollment engine (service)**

- Prerequisites from `academic_records`, registration windows, program membership, waitlist on capacity, financial hold, add/drop, withdraw with refund percent, live-session clash *warning*.
- Student register / drop / withdraw / degree-audit pages work.
- Tested in `EnrollmentEngineTest`.

**Attendance (S3) — strongest LMS-adjacent subsystem**

- `class_sessions` independent of Zoom. PRESENT / ABSENT / LATE / EXCUSED.
- Optimistic lock (`lock_version` → 409). Self check-in codes. Policy. Roster CSV + birthdays.
- Gradebook attendance component. Web + `/api/v1` teach and student routes.
- Nine feature files + `AttendanceApiTest`.

**Communications spine (S2)**

- Draft → publish → targeting (offering / program / semester / role / user) → in-app + mail.
- Email templates with merge-field allowlist. Per-event channel preferences. Delivery log + CSV + open pixel.
- WhatsApp channel is registered and **explicitly unimplemented** (`ChannelDispatcher` writes `error: unimplemented`).
- Teach publish flow + student inbox + admin report. API coverage.

**Assessment engine (backend)**

- Banks, shuffle, bank draw, timed attempts, autosave, server timer, `assessments:auto-submit-expired`.
- Objective grader (MCQ, T/F, matching with partial credit, numeric, fill-blank, ordering).
- Essay AI *suggestion* via Gemini when keyed; otherwise no-op.
- Assignment late penalty + resubmission versioning (`AssignmentResubmissionIntegrityTest`).
- Gradebook weighted compute → submit → lock → `academic_records` + `cached_gpa`.

**Finance ledger (backend)**

- Integer minor units, `Currency` EGP/USD, four-bucket wallet, split pay, invoices on enrollment, refunds, donations, receipt serial.
- `FinanceFlowTest` covers checkout, webhook idempotency, drop refund.
- **Gateways are mock.** `PAYMENTS_MOCK_AUTO_COMPLETE` defaults true.

**Credentials verify**

- Issue transcript / program certificate / standalone certificate. Public `/verify/{token}`.
- `file_url` is an HTML placeholder, not a PDF.

**Live Zoom (degraded)**

- Schedule, join window, 24h / 15m reminders, webhook import, concurrent-host guard.
- Without Zoom keys, `ZoomClient` returns mock URLs. Recurrence service has **no HTTP route**.

### 3.2 Partial — finish these before calling the product “complete”

These already have tables and services. They fail a client demo or a registrar’s day-to-day.

| Area | What works | What is missing |
|---|---|---|
| Programs / courses | Create, attach curriculum, prerequisites, catalog interest | **No edit/delete routes** even though `ProgramService::update` / `CourseService::update` exist. `max_semesters_to_graduate` and `year_level` are stored and unused. |
| Semesters | Create year + semester; enrollment reads windows | No edit. Semester `status` is not checked on register (DRAFT offerings appear). |
| Offerings | Create, staff, pricing, clone, weeks/content | No status workflow after create. No remove-staff. Waitlist page has **no nav link**. Academic admin got **403** on `/admin/offerings/{id}/waitlist` in the live crawl. |
| Enrollment admin | Override + financial-hold **routes** | **No Blade forms.** Caps count all `ENROLLED` rows, not the current term. `COMPLETED` is never written. Audit enrollments have no UI path. |
| Degree audit / graduation | Read-only checklist | Electives shown in UI but `CredentialService::programRequirementsMet` checks **required courses only**. `StudentProgramStatus::Completed` is never set. No graduation application. |
| Course player | `/learn/*` item-level + `/courses/*` week-level | **Two progress formulas** write the same `progress_percent`. `/courses/*` has no permission middleware. `CoursePlayerService::mapItem()` can fall back to the *first* released assessment. **Zero content items in the demo seed.** |
| Teach hub | Index lists staffed offerings; attendance grid works | **`GET /teach/{offering}` 500s** — loads `weeks.contentItems` but `Week` only has `items()`. Content authoring still lives under `/admin/offerings`. |
| Exam runner | Timed MCQ / T-F / short / essay / numeric | **No UI** for MCQ multi, matching, ordering, file upload (grader already supports them). `released` is not checked in `AttemptService::start()`. Proctor flags stored, not enforced. `results_visibility` unused. |
| Gradebook UI | Lock / reopen / post records | Summary table only — no per-item grid, no CSV export, no drop-lowest / curve / extra credit. Attendance/discussion kinds missing from the add-component `<select>`. |
| Assignments | Submit + grade + versions | Student form pastes a `file_url`; `POST /api/uploads` is unused. |
| Discussions | Threads, mentions, auto-score, moderate | `PrivateToInstructor` never filtered. No pagination. Attachments unused. Hard-coded English placeholders. |
| Admissions admin | Create forms, decide | Forms are create-only. `SUBMITTED` status is skipped (draft → under review). `is_reviewer` not editable after user create. Unique `(applicant_id, program_id)` blocks reapply. |
| Users admin | Create + suspend | No edit, no role removal, no addresses / emergency contacts / custom fields. |
| Credentials | Issue + verify | No PDF (DomPDF planned in S4). Admin form wants raw ULIDs. Transcript page is a live query, not an issued snapshot. |
| Finance UI | Student wallet + admin invoice/manual/verify | No student refund request route. Receipts are HTML. Donations mark `Completed` immediately. No payment plans, aging, or GL export. |
| Mail | `TransactionalMailer` + templates | Plain `Mail::raw`. No unsubscribe footer. Open-tracking URL is guessable (`/communications/open/{log}`). |
| Mobile API | S6 student waves + S8 instructor + S9 events/quiz on polling are on `main` @ `acb20cb` | Snapshot (`7cec603`) had auth / me / branding / comms / attendance only. Leftover: advisor what-if on the student API. |
| Ops | Health, backup command, scheduled jobs | Scheduled-tasks UI omits `communications:fire-reminders`. Observability is counts only. OTP is logged in plaintext in dev. Sanctum tokens never expire. |

**Roadmap already on `main` (do not re-implement):** S4, S5, S6 A–E, S7, S8, and the S9 domain (events + live quiz on polling). Prompts #10, #11, #14, #18, #19, #20 are cancelled.

**Leftover after `acb20cb` (not a new S-phase):**

1. Discussion attachments via `ObjectStorageService` (column exists; upload path unused).
2. Advisor what-if on the student API (web what-if exists).
3. Program-level academic-standing overrides (school-wide thresholds exist).
4. Paymob `integration_id` in `config/services.php` / `.env.example`.
5. `permissions:sync` for newer keys after deploy.
6. Optional Reverb; WhatsApp driver; lockdown browser; native apps; other [PARKING-LOT.md](../PARKING-LOT.md) items.

### 3.3 Suitable to add to become Populi-like

Populi is the usual comparison for small theological / specialist colleges: one cloud SIS+LMS+billing+aid product ([populi.co/features](https://populi.co/features/), [Populi intro](https://www.populiweb.com/blog/2022/05/intro-to-populi/)). SPIMS already matches Populi’s *shape* (one student record, built-in LMS, admissions, billing, donations). It does not yet match Populi’s *breadth*.

Recommended in three bands. Effort is technical, not calendar time.

#### Band A — high value on the current schema (S4/S5 already on `main`)

| Capability | Why clients ask | Fit |
|---|---|---|
| Wire program/course **edit** and offering **status** | Registrars cannot maintain catalog today | Existing services, missing routes |
| Admin UI for hold / override / waitlist | Engine exists; clients cannot click it | Blade + permission already there |
| Semester-scoped credit caps | Current cap blocks cross-term registration | Filter `enrollments` by offering semester |
| Mark enrollment / program **completed** | Transcripts and certificates stay empty otherwise | Write on grade lock + S4 close |
| Gradebook **CSV export** + per-item grid | Instructors live in spreadsheets | `GradebookService` already computes |
| Unify `/learn` and `/courses` | Two progress models confuse demos | Delete or wrap `CoursePlayerService` |
| Connect assignments to `/api/uploads` | File submit is not real | Ownership check on stored path |
| Official transcript **request → issue snapshot PDF** | Employers expect a sealed PDF | S4 renderer + existing `/verify` |
| Enrollment verification letter | “Is this student enrolled?” | New credential type |
| Transfer credit (manual `academic_records`) | Adult students bring prior study | Allow records without `enrollment_id` |
| Student profile / custom fields | Admissions forms are not a permanent record | JSON/EAV on `users` |
| Bulk CSV import (users, courses, roster) | Opening a term by hand does not scale | Medium |
| Live PayPal (USD) + Paymob/Cashier (EGP) | Cannot take real tuition | Replace `GatewayRouter` mock |
| Payment plans / scheduled charges | Seminaries bill by installment | New tables on `invoices` |
| Dunning (overdue mail via S2) | `due_date` is set and never chased | Job + template |
| Course evaluations (end-of-term survey) | Populi staple; faculty review | New small subsystem (also Khedma G-14) |
| Academic standing / SAP flags | “Probation” after GPA drop | Rules on `cached_gpa` |
| What-if degree audit | Advising lite | Reuse `DegreeAuditService` with a hypothetical course list |
| Calendar / due-date view | Students ask “what’s due?” | Dashboard already has due assessments |
| Reporting pack | Headcount, retention, revenue, attendance % | Query layer + CSV; no new domain |

#### Band B — Populi features that fit a Coptic online school (next product cycle)

| Capability | Notes |
|---|---|
| **Advising** | Advisor assignment, notes, registration locks, at-risk flags. Populi’s differentiator. High effort; no schema today. |
| **Scholarships / institutional aid** | Award → disburse onto the wallet/invoice. Do **not** build US Title IV / ISIR / COD unless the school becomes Title IV. |
| **Donor CRM** | Donations exist; campaigns, appeals, acknowledgements, recurring gifts do not. |
| **Course copy / master templates** | Reuse weeks and banks across offerings. Medium. |
| **Rubrics + SpeedGrader-style queue** | Needed once S5 lands. Medium–high. |
| **SMS / WhatsApp** | Channel interface exists; implement the WhatsApp driver only when a provider is chosen. |
| **SSO (SAML/OIDC) + MFA** | Expected by diocesan IT and by students who already have Google accounts. |
| **Outbound webhooks + API keys** | So parish sites and accounting can subscribe. |
| **GDPR/export + field-level privacy** | Parent/guardian is explicitly out of scope; student export is not. |
| **Room / meeting-pattern scheduling** | Only if the school adds in-person cohorts. High. Online-only can skip. |

#### Band C — Populi has these; SPIMS should **not** copy soon

| Capability | Why skip |
|---|---|
| US Federal financial aid (FAFSA, Pell, COD, 1098-T) | US-regulatory product. SPIMS is EGP/USD seminary billing. |
| Library ILS + bookstore POS | Separate products; donation + invoice cover the need. |
| Housing / dorms | Online school. |
| SCORM / LTI / H5P / xAPI | High cost; Vimeo + files cover current pedagogy. Revisit if partners bring packaged courses. |
| Lockdown-browser proctoring | Parked. S5 does event log + terminate only. |
| Native iOS/Android apps | API is in the plan; apps stay parked. |
| Multi-school / multi-tenant | Explicitly out of scope (`PARKING-LOT.md`). |
| AI tutor / content generation | Beyond translation + essay suggest. Parked. |

---

## 4. Full path map (what a client actually clicks)

234 routes. Below is the **usable** surface after `migrate:fresh --seed`, grouped by persona. Paths that 403’d or 500’d in the live crawl are marked.

### Public

| Path | Result |
|---|---|
| `/` home | 200 |
| `/login`, `/register`, `/forgot-password` | 200 |
| `/catalog` | 200 — lists TH101 and the rest |
| `/health`, `/up` | 200 JSON |
| `/verify/{token}` | 200 (invalid token still renders the verify page) |
| `POST /api/v1/login` | 200 token envelope |
| `POST /api/webhooks/payments`, `/api/webhooks/zoom` | CSRF-excluded, HMAC |

### Every authenticated role

`/dashboard`, `/catalog`, `/settings`, `/notifications`, `/announcements` — 200 for all eight personas.

### Student (`student1@spims.test`)

| Path | Result | Demo content |
|---|---|---|
| `/applications` | 200 | One ACCEPTED DIP-THEO row (no field answers) |
| `/enrollments` | 200 | TH101 + BI101 enrolled, BI102 waitlisted |
| `/learn/{offering}` | 200 | Empty weeks |
| `/courses/{offering}` | 200 | Empty player |
| `/degree-audit/{studentProgram}` | 200 | Required courses unmet (no academic records) |
| `/grades`, `/transcript` | 200 | Empty |
| `/finance` | 200 | Empty wallet / no invoices |
| `/live`, `/attendance` | 200 | Empty |
| `/offerings/{id}/discussions` | 200 | Empty board |
| `/learn` (no id) | 404 | Expected — needs an offering |

### Instructor (`ins1@spims.test`)

| Path | Result |
|---|---|
| `/teach` | 200 — TH101, BI102, CH101, FREE1 |
| `/teach/{TH101}` | **500** — `Week::contentItems` missing (relation is `items`) |
| `/teach/{TH101}/attendance` | 200 — no sessions yet |

### Academic admin (`aca@spims.test`)

200: `/admin/programs`, `/courses`, `/offerings`, `/semesters`, `/grading-schemes`, `/assessment-templates`, `/translations`, `/attendance/report`, `/admin/offerings/{id}`, `/gradebook`, `/live`.
403: `/admin/theme` (ops-only), `/admin/offerings/{id}/waitlist` (enrollment-override permission).

### Administrative admin (`adm@spims.test`)

200: users, applications, application-forms, semesters, theme, finance (read).
403: email-templates, grading-schemes, assessment-templates, translations, attendance report.

### Finance (`fin@spims.test`)

200: `/admin/finance`, `/admin/finance/reports`, `/donate`. Screens are empty (0 invoices).

### Super admin

200 on everything above plus `/superadmin`, `/superadmin/audit|security|observability|scheduled-tasks`, `/roles-hub` (~670 KB permission matrix).

---

## 5. Defects found in this review (actionable)

These were confirmed against running code, not inferred from docs.

1. **`TeachController::show` 500** — eager-load `weeks.contentItems` but `Week` defines `items()`. File: `app/Http/Controllers/Teach/TeachController.php`. Instructors cannot open an offering workspace. Attendance sub-routes still work.
2. **Dual course players** — `CoursePlayerService` progress = weeks completed; `LearningProgressService` progress = items completed. Same column.
3. **Unreleased exams are startable** — `AttemptService::start()` does not check `assessment.released`.
4. **Wrong-assessment fallback** — `CoursePlayerService::mapItem()` can bind the first released assessment on the offering.
5. **Credit caps not term-scoped** — `EnrollmentService::assertCanRegister` counts every `ENROLLED` row.
6. **Program certificate ignores electives** — `CredentialService::programRequirementsMet`.
7. **AuthorizationException logged as ERROR** on expected 403s (handler reports them). Noisy logs; not a hole.
8. **OTP plaintext in logs** when `MAIL_MAILER=log` (`OtpService`).
9. **Default webhook secrets** (`paypal-test`, `paymob-test`, `cashier-test`, `zoom-test`) if env is unset.
10. **`PAYMENTS_MOCK_AUTO_COMPLETE` defaults true** — dangerous if a production `.env` omits it.
11. **Sanctum tokens never expire** (`config/sanctum.php`).
12. **Public communication open-pixel** — anyone with a log ULID can mark a mail opened.
13. **Drop/withdraw routes** have no permission middleware (ownership check is in the service).
14. **Role-name checks** remain in `OfferingAccessService`, `TeachAccessService`, `ApplicationService` — against the project rule (permission keys only).
15. **Demo seed has no classroom or money data** — see [demo-accounts.md](demo-accounts.md) §Gaps. Finance, teach content, attendance, and credentials demos are empty after a fresh seed.

---

## 6. Test and CI posture

The gate is the right shape: one suite per domain, Auth early, OpenAPI coverage on `/api/v1`, PostgreSQL migrate job in parallel.

Gaps in the *test* surface (not the product):

- No dedicated `DegreeAudit` or `Gradebook` suite (logic is folded into Enrollment / Assessment).
- No HTTP test for `GET /teach/{offering}` — that is why the 500 was not caught.
- `phpunit.xml` `Feature` suite excludes every subdirectory and is empty.
- Tests force `SEED_DEMO_DATA=false`; they never see the client seed. Add one `DemoDataSeeder` smoke test so a broken demo account fails CI.
- Audit coverage is not parameterized over the service-method list (the execution-order “fourth invariant”).

---

## 7. How this compares to the in-repo plans

| Source | Still accurate? |
|---|---|
| [academic-roadmap/README.md](academic-roadmap/README.md) — S0–S8 done + S9 domain | Yes (as of `acb20cb`; the 2026-09-06 “S4/S5 next” line is obsolete) |
| [gap-analysis.md](academic-roadmap/gap-analysis.md) written at `d764d1e` | Historical. G-03/G-09–G-12/G-15/G-21/S0/S1 defects it names are fixed. Keep it as baseline, not as “today”. |
| [portal-design-gap-analysis.md](portal-design-gap-analysis.md) (2026-07-29) | **Stale in places.** It still says Course Player, Teach hub, grades, announcements, finance reports are absent. Those pages exist now (thin). The Sacred Academic visual-system gap is still real. |
| [client-system-overview.md](client-system-overview.md) | Honest about *capability*. Overstates *demo-readiness* (invoices, Vimeo, certificates) unless someone has entered data by hand. |
| [PARKING-LOT.md](../PARKING-LOT.md) | Correct: WhatsApp driver, native apps, multi-tenant, parent role, hard lockdown browser stay out. |

---

## 8. Recommended build order (after this review)

**Do not start S4, S5, S6, S7, S8, or S9 as greenfield.** Those phases (plus S6 Wave E and the S9 domain on polling) are on `main` @ `acb20cb`. `gradebook.reopen` on the instructor API and applicant `WITHDRAWN` already landed (`e50faa8` and after).

Historical P0/P1 from the 2026-09-06 snapshot (teach 500, demo seeder, operator CRUD) may already be fixed — verify against current `main` before opening a prompt. Remaining work that is still open:

1. **Discussion attachments** through `ObjectStorageService` (if still unused).
2. **Advisor what-if** on the student `/api/v1` (web exists).
3. **Program-level standing overrides** (school-wide hundredths exist).
4. **Paymob `integration_id`** wiring in config / env.
5. **`permissions:sync`** so newer keys land on deployed role rows.
6. **Optional Reverb**, WhatsApp driver, and other parked items — only when named.

Do not start library, bookstore, housing, Title IV, or SCORM. They are Populi features for a different school.
