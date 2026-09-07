# SPIMS implementation plan — full SIS + LMS with a mobile API

**Executed in:** this repository
**Closes:** gaps G-01 … G-20 from [`gap-analysis.md`](gap-analysis.md)
**Companion:** [`mobile-api-spec.md`](mobile-api-spec.md) defines the wire contract this plan builds.

### Status vs this audit (2026-09-07)

Against `main` @ `acb20cb`, S0–S8, S6 Wave E, and the S9 domain (events + live quiz on polling) are already on `main`. Leftover `cursor/s4*`–`s8*` remotes are ancestors with nothing to cherry-pick — see [`s-branch-reconciliation.md`](s-branch-reconciliation.md). Still open: Reverb (optional), WhatsApp driver, lockdown browser, and native apps. `gradebook.reopen` on the instructor API and applicant `WITHDRAWN` already landed (`e50faa8` and after).

---

## 0. Ground rules

These are this repo's own rules ([`CLAUDE.md`](../../CLAUDE.md)), not new ones. Every phase below obeys them, and a
PR that breaks one should be rejected regardless of what it delivers.

1. Authorization goes through `AuthorizeService` against a key in `config/permissions.php`. No
   role-name string comparison in a controller. **Amended by S0:** every check on a scoped resource
   must also pass that resource.
2. Every mutation runs inside a service wrapped by `AuditLogWriter::withAudit()`.
3. Money is integer minor units plus a `Currency` enum. No floats, no conversion.
4. Every user-facing string is localized in `ar`, `en`, and `fr`. Arabic is primary and RTL-first.
5. Migrations are additive. No column is dropped or renamed in this plan.
6. Tests ship in the same PR as the behaviour, and the CI gate in `.github/workflows/ci.yml` must
   pass before merge.
7. Mailers and third-party keys are optional in development; a missing key degrades, never blocks.
8. No npm build step. UI is Blade, Bootstrap, and Alpine, served from `public/`.

Two conventions this plan adds:

9. **ULID primary keys** on every new table, matching the existing schema. Foreign keys use
   `foreignUlid()`.
10. **One phase, one PR series, one CI-green merge.** A phase is not done until its web surface, its
    API slice, and its tests are all in.

### Phase dependency order

```
S0 authorization scoping ──┬─> S1 API foundation ──┬─> S6 student API waves
                           │                       └─> S8 instructor API
                           ├─> S2 comms spine ─────> S5 assessment completion
                           ├─> S3 attendance + roster
                           ├─> S4 completion + credentials
                           └─> S7 team projects
S9 realtime (live quiz, events) — depends on S1 and S2, otherwise independent
```

S0 and S1 are hard prerequisites. S2 through S5 may proceed in parallel once S0 lands. S6 requires
the domain phase for each wave it exposes. S9 is last because it introduces new infrastructure.

---

## S0 — Resource-scoped authorization (G-02) ✅ **Done**

**Delivered:** commit [`a3019f9`](https://github.com/RobsGeorge/spims-edu/commit/a3019f9). Verified on `main` @ `d764d1e`: 131 → 139 passing,
608 assertions; `ResourceScopeTest` fails 4 of 8 against the pre-change code; pint clean;
PostgreSQL 16 gate green.

**Why first.** Every later phase adds routes and an API. Adding them over an authorization layer
that ignores resource scope multiplies an existing vulnerability rather than containing it.

### Two corrections found while building it

**The plan's fail-closed rule was too blunt.** It said any `O` grant reached without a resource
should throw. That would have broken `profile.edit_own`, `finance.pay`, `assignments.submit`,
`assessments.take`, `enrollment.register` and `discussions.post` — all `O`, none offering-scoped,
because for a student "own" means *on my own behalf*. A level alone cannot carry that distinction.
The shipped design adds `config/permission_scopes.php`, naming the 14 offering-scoped keys and the
roles (`INSTRUCTOR`, `TA`) whose grants they confine; everything else keeps working unchanged.

**The membership read holes were already fixed.** The plan carried forward this repo's own note that
assignment detail and discussion threads were readable by any authenticated user. Both are now
guarded by `OfferingAccessService::assertCanAccessAssignment()` and `assertCanAccessDiscussion()`,
added in their D3 phase after that note was written. No change was needed. `DiscussionService::
ensureBoard()` is still called on a `GET`, which remains worth moving to an audited mutation path,
but it is not an access-control hole.

### Behaviour change

`AuthorizeService::authorize()` currently treats `O` (own) as equivalent to `F` (full). After this
phase, `O` means "allowed only when the actor is scoped to this resource", and passing no resource
where the matrix says `O` is a programming error that fails closed.

### Build

- Introduce `App\Support\Scope\ResourceScopeResolver` with
  `scopedTo(User $user, mixed $resource): bool`. It resolves the offering behind any supported
  resource type and checks `offering_staff` membership for staff, `enrollments` for students.
  Register resolvers per model (`CourseOffering`, `Assessment`, `Assignment`,
  `AssignmentSubmission`, `AssessmentAttempt`, `GradebookComponent`, `DiscussionThread`,
  `LiveSession`, `Enrollment`) so new models opt in explicitly.
- Rework `AuthorizeService::authorize()`:
  - `F` → allow.
  - `R` → allow for read actions.
  - `O` → allow only when `$resource !== null` and `ResourceScopeResolver::scopedTo()` is true.
  - `O` with `$resource === null` → throw. Fail closed, and make it loud in tests.
  - Super Admin still short-circuits, as today.
- Add `App\Http\Middleware\RequireScopedPermission` (alias `permission.scoped:{key},{routeParam}`)
  that resolves the bound model from the route and forwards it. Convert every `/admin/*` route
  whose matrix level is `O` to use it.
- Push the resource into the service layer: every `authorize()` call in `app/Services/**` that acts
  on an offering-scoped entity gains its third argument.
- Fix the two membership holes SPIMS already documented: `AssignmentController::show()` and
  `DiscussionController::showBoard()` / `showThread()` must assert enrollment or staffing.
- Move `DiscussionService::ensureBoard()` off the `GET` path into an audited mutation.

### Tests (`tests/Feature/Auth/`, suite `Auth`)

- `ResourceScopeTest` — an Instructor staffed on offering A is denied every mutating route on
  offering B: gradebook lock, submit, reopen, component add, assessment create, attach, release,
  answer grade, submission grade, live schedule, attendance import and override, discussion
  configure, moderate, grade.
- `ScopeFailClosedTest` — calling `authorize($user, $keyWithOwnLevel)` with no resource throws.
- `AuthorizeServiceTest` — extend: `F` unaffected; Super Admin unaffected; multi-role union still
  resolves to the most permissive level.
- `MembershipReadTest` — a non-enrolled, non-staff user gets 403 on assignment detail, discussion
  board, and discussion thread.
- Regression: the whole existing suite must stay green. Failures here are the point — they mark
  every place that silently relied on unscoped access.

### Acceptance

An Instructor can operate only offerings listed in `offering_staff` for their user. A TA has the
same scope minus `gradebook.lock`. An Academic Admin retains `F` where the matrix grants it. No
existing green test is deleted to make this pass.

---

## S1 — API foundation (G-01, part 1) ✅ **Done**

**Delivered:** commit [`40bb283`](https://github.com/RobsGeorge/spims-edu/commit/40bb283). Verified on `main` @ `d764d1e`: 139 → 176 passing,
712 assertions; independently reviewed by a second agent with no prior context, which found two real
gaps (both fixed) and confirmed three of the commit's technical claims against framework source.
The commit message carries the full detail.

**Follow-up fix:** commit [`27400aa`](https://github.com/RobsGeorge/spims-edu/commit/27400aa). `login` returned a 500 on PostgreSQL because
Sanctum's stock migration types `personal_access_tokens.tokenable_id` as a bigint while `users.id`
is a ULID. SQLite's per-value type affinity hid it from the suite; the PostgreSQL gate caught it.

**Deliverable:** an empty but complete `/api/v1` — auth, conventions, and one reference endpoint —
so that later phases add routes without relitigating shape.

### The auth mechanism this plan assumed was wrong

The original wording below (kept for the historical record) said login "mirrors the web OTP
lifecycle." That's how Khedma works; it is not how this app works. `AuthService::login()` has
always been email + password — OTP exists only for email verification and password reset. Building
against the wrong contract was caught before writing code, by reading `LoginController` and
`AuthService` directly rather than trusting the plan's own prose. Shipped instead:

```
POST /api/v1/login  { email, password, device_name? } -> { data: { token, token_type, user } }
POST /api/v1/logout                                    -> 204, revokes only this token
GET  /api/v1/me                                        -> the caller's profile + roles
GET  /api/v1/branding                                  -> public, safe pre-login
```

### Two more corrections, found by an independent review

A second agent reviewed the diff cold — no memory of building it — specifically hunting for bugs in
the exception-handling and locale logic, since that was the trickiest part. It found two real,
reproducible gaps, both fixed in the same commit:

1. **`Handler::codeForStatus()` had no 401/403 mapping.** Harmless for this app's own
   `AuthorizationException` (hardcoded to `FORBIDDEN`, never reaches that method), but Laravel's own
   `Illuminate\Auth\Access\AuthorizationException` — what Policies, `Gate::authorize()`, and the
   `can:` middleware throw — has no `render()` of its own, gets converted to
   `AccessDeniedHttpException`, and *does* reach it. Reproduced: with the mapping reverted, the same
   exception yields `code: "ERROR"` instead of `code: "FORBIDDEN"` for an identical 403. Two
   exception types, one HTTP status, two different codes — the exact thing "one error shape" exists
   to prevent, the moment either type is actually thrown. This matters directly for S8: if instructor
   endpoints ever authorize via a Policy instead of `AuthorizeService`, this is the bug that would
   have shipped silently.

2. **`OpenApiCoverageTest` filtered on route *name*, not URI.** A route registered under `/api/v1/*`
   with a name that didn't happen to start with `api.v1.` — a typo, or one added outside the
   `Route::prefix('v1')->name('api.v1.')` group — passed the coverage test while being live,
   reachable, and completely undocumented. Reproduced exactly as described; fixed to filter on
   `$route->uri()` instead.

The review also surfaced one issue deliberately left unfixed:
**`AuthorizeService::authorize(null, ...)` throws this app's own `AuthorizationException` (whose
`render()` is hardcoded to 403) for what is semantically a 401** — the thrown message is the
unauthenticated string, wrapped in a `FORBIDDEN` envelope. This is pre-existing behavior in code S0
shipped untouched, and the existing test (`AuthorizeServiceTest::guest_raises_unauthorized`) asserts
only the exception *type*, never its rendered status, so the mismatch was never caught before. Not
reachable via any S1 route today. Fixing it means changing what `AuthorizeService` throws for a null
actor — its own blast radius, belonging in its own dedicated patch, not folded into an
API-foundation commit as a side effect of review. **Flagged here for whoever picks up S0's residual
surface or starts wiring Policies in a later phase.**

### Build (as originally planned — superseded by what shipped, above)

Kept for the historical record. The OTP bullet and the `login/verify` endpoint were wrong for this
app; the `SetLocale` reuse and the `ar` default were also changed. Everything else landed as written.

- `routes/api.php`: `v1` prefix, `App\Http\Controllers\Api\V1` namespace. Keep the legacy
  `GET /api/user` untouched.
- **Token auth.** `POST /api/v1/login` mirrors the web OTP lifecycle in
  `app/Services/Auth`: credentials → OTP challenge → `POST /api/v1/login/verify` → Sanctum personal
  access token. Reuse `OtpToken`; do not fork the flow. Throttle `10,1` on login and `20,1` on
  verify, matching Khedma. Tokens carry abilities derived from the user's roles
  (`role:INSTRUCTOR`, `role:STUDENT`, …) so a stolen student token cannot reach instructor routes
  even if the permission matrix later changes.
- `POST /api/v1/logout` revokes the current access token only.
- **Response envelope.** A `data` key for payloads and a `meta` key for pagination. Collections are
  page-based (`?page=`, `?per_page=`, default 20, max 100).
- **Errors.** One shape for every failure, produced by an exception handler mapping:
  `AuthorizationException` → 403, `ValidationException` → 422 with a `errors` map,
  `ModelNotFoundException` → 404, throttle → 429 with `Retry-After`.
- **Localization.** An `Accept-Language` header of `ar`, `en`, or `fr` sets the response locale for
  that request; absent the header, fall back to the user's stored preference, then to `ar`. Reuse
  `SetLocale` rather than writing a second resolver.
- **Resources.** `App\Http\Resources\Api\V1\*` for every payload. Controllers stay thin: validate,
  call the existing service, return a resource. No business logic is re-implemented for the API —
  this is the rule that keeps web and mobile from diverging.
- `GET /api/v1/me` and `GET /api/v1/branding` as the reference endpoints (branding already exists as
  a web route; the API version returns the same `ThemeTokens` payload).
- Publish `docs/api/openapi.yaml`, generated or hand-maintained, and assert in a test that every
  registered `api/v1` route appears in it. Khedma has no OpenAPI spec; SPIMS should, because the
  mobile client is a separate codebase.

### Tests (`tests/Feature/Api/`, suite `Api`)

- `ApiAuthTest` — login issues an OTP, verify returns a token, the token authenticates `/me`,
  logout revokes it, a revoked token 401s, throttles fire.
- `ApiTokenAbilityTest` — a token minted for a Student is rejected on an instructor-ability route.
- `ApiEnvelopeTest` — success, validation, authorization, not-found, and throttle shapes.
- `ApiLocaleTest` — the same endpoint returns `ar`, `en`, and `fr` copy per `Accept-Language`.
- `OpenApiCoverageTest` — every `api/v1` route is documented.

### Acceptance

A client can authenticate, read `/me` in Arabic, and receive a predictable error envelope. No
domain endpoints exist yet, and that is correct.

---

## S2 — Communications spine (G-09, G-10, G-11, G-12) ✅ **Done**

**Why early.** Announcements, reminders, graduation notices, and project deadlines all need one
delivery path with one log. Building them per-feature is how delivery reporting becomes impossible.

### Schema (additive)

| Table | Purpose |
|---|---|
| `announcement_revisions` | edit history per announcement |
| `announcement_targets` | polymorphic audience: offering, program, semester, role, or explicit user list |
| `announcement_deliveries` | one row per recipient per channel; `status`, `sent_at`, `read_at`, `opened_at`, `error` |
| `communication_logs` | every outbound message: `type`, `channel`, `recipient_id`, `subject`, `locale`, `status`, `provider_message_id`, `opened_at` |
| `email_templates` | `scope_type` + `scope_id` (nullable = global), `key`, `locale`, `subject`, `body`, `updated_by_id` |
| `notification_preferences` | `user_id`, `event_key`, `channel`, `enabled` |
| `notification_reminders` | user-scheduled reminders: `user_id`, `subject_type`, `subject_id`, `remind_at`, `sent_at` |

Extend `announcements` additively: `status` (`DRAFT`/`PUBLISHED`), `published_at`, `published_by_id`,
`is_banner`, `banner_expires_at`, `body_ar`/`body_fr` if not already translatable.

### Services

- `App\Services\Communications\ChannelDispatcher` — a channel registry. `in_app` and `mail` ship;
  `whatsapp` is registered as an interface with no driver, so the parked item stays parked without
  the abstraction having to be retrofitted later.
- `AnnouncementService` — `draft`, `update` (writes a revision), `publish` (resolves targets, fans
  out through the dispatcher, writes deliveries), `resendEmail`, `dismissBanner`.
- `EmailTemplateService` — resolve a template by scope and locale with fallback (course → global →
  lang file), render with a variable allowlist, and `preview` without sending.
- `CommunicationLogWriter` — called by the dispatcher; never by feature code directly.
- Wire `TransactionalMailer` to log through it, so existing sends appear in the report immediately.
- **Fix the `notify_email` defect first. ✅ Done — commit [`cdcc89b`](https://github.com/RobsGeorge/spims-edu/commit/cdcc89b).**
  `NotificationService` ignored `users.notify_email` and always sent when `alsoEmail = true`, so the
  settings toggle was inert. The patch gates the mail channel on the preference, defaulting to
  opted-in. When `ChannelDispatcher` lands, route sends through it and treat the legacy boolean as
  the default for the `mail` channel until users have per-event rows.

### Permissions (`config/permissions.php`)

`announcements.view` (all authenticated), `announcements.manage` (`INSTRUCTOR`/`TA` = `O`,
`ACADEMIC_ADMIN` = `F`), `announcements.publish` (`INSTRUCTOR` = `O`, `ACADEMIC_ADMIN` = `F`; TA
excluded), `communications.report` (`ADMINISTRATIVE_ADMIN`/`ACADEMIC_ADMIN` = `R`),
`email_templates.manage` (`ACADEMIC_ADMIN` = `F`, `INSTRUCTOR` = `O`),
`notifications.preferences` (all = `O`).

### Web

Teach hub: announcements tab gains edit, publish, and resend. Student: announcements index, detail,
and a dismissible banner in the shell. Admin: communications report with filters and CSV export;
email template editor with live preview; a settings page for notification preferences and reminders.

### API (`mobile-api-spec.md` §Student A, §Instructor A)

`GET /announcements`, `GET /announcements/{id}`, `POST /announcements/{id}/dismiss-banner`,
`GET /notifications`, `GET /notifications/{id}`, `POST /notifications/{id}/read`,
`POST /notifications/mark-all-read`, `GET|PUT /notification-settings`;
instructor `POST /offerings/{id}/announcements`, `PUT /announcements/{id}`,
`POST /announcements/{id}/publish`.

### Tests

- `tests/Feature/Communications/AnnouncementLifecycleTest` — draft → edit (revision written) →
  publish → deliveries created per targeted recipient → resend does not duplicate.
- `AnnouncementTargetingTest` — offering, program, role, and explicit-user audiences each resolve to
  the right recipient set, and a student in neither gets nothing.
- `AnnouncementScopeTest` — an Instructor cannot publish to an offering they do not staff (S0).
- `CommunicationLogTest` — every dispatch writes a log row; the report filters and exports; the
  open-tracking pixel sets `opened_at` once and is idempotent.
- `EmailTemplateTest` — scope fallback order, locale fallback, variable allowlist rejects unknown
  variables, preview sends nothing.
- `NotificationPreferenceTest` — a disabled channel suppresses that channel and only that channel;
  reminders fire once at `remind_at`.
- `NotifyEmailRespectedTest` — a user with `notify_email = false` receives the in-app notification
  and **no** mail, and no `EMAIL` channel row is written. Assert this against
  `NotificationService`'s existing default-`alsoEmail` call path, since that is the one that is
  currently wrong.
- `tests/Feature/Api/CommunicationsApiTest` — the endpoints above, including a student being unable
  to publish.

### Acceptance

An instructor drafts and publishes an announcement to their offering; every enrolled student
receives it in-app and by mail in their own locale; the administrator can produce a CSV proving it;
a student who disabled email still gets the in-app copy.

---

## S3 — Attendance as an SIS record, roster, and sessions (G-03, G-04, G-15, G-21) ✅ **Done**

**The most consequential SIS phase.** It decouples attendance from Zoom.

### Schema (additive — `attendance_records` is preserved and back-filled, never dropped)

| Table | Purpose |
|---|---|
| `class_sessions` | a scheduled meeting: `offering_id`, `title`, `scheduled_start`, `duration_minutes`, `mode` (`IN_PERSON`/`ONLINE`/`HYBRID`), `location`, nullable `live_session_id`, `attendance_closed_at`, `notify_students` |
| `attendance_policy` | per offering or global: `min_percentage`, `late_grade_percentage`, `counts_toward_grade`, `is_enabled` |
| `attendance_entries` | `class_session_id`, `student_id`, `status` (`PRESENT`/`ABSENT`/`LATE`/`EXCUSED`), `excuse_reason`, `minutes_attended`, `source` (`MANUAL`/`ZOOM_IMPORT`/`SELF_CHECK_IN`), `recorded_by_id`, `recorded_at`, `lock_version` |
| `attendance_check_in_codes` | `class_session_id`, `code`, `expires_at`, `max_uses`, `uses` |
| `session_notification_targets` | who was notified about a session, so reminders are not re-sent |

`users` also needs an additive `date_of_birth` column: SPIMS stores no birth date at all, so the
birthdays feed below cannot be built as a query over existing data. Add the column nullable, expose
it on the profile form, and treat the feed as sparse until it is populated.

Add `class_session_id` to `attendance_records` and treat the old table as the Zoom import staging
area, or migrate its rows into `attendance_entries` with `source = ZOOM_IMPORT`. Either is additive;
prefer migration so there is one read path.

`lock_version` is the detail worth copying deliberately: two instructors marking the same roster
simultaneously must not silently overwrite each other. Khedma added it in
`2026_08_11_000002_add_lock_version_to_attendance.php` after hitting exactly that.

### Services

- `AttendanceService` gains `openSession`, `markRoster` (bulk, compare-and-swap on `lock_version`,
  rejecting a stale write with a 409), `markOne`, `fillMissing` (default remaining students to
  absent), `closeSession`, `reopenSession` (audited, permissioned separately), `excuse`,
  `selfCheckIn(code)`, `percentFor(student, offering)`, and `report(offering, filters)`.
- Keep `importFromZoom` and `override`, now writing into `attendance_entries`.
- `GradebookService::componentPercent()` switches to `AttendanceService::percentFor()`; the
  attendance gradebook component keeps working unchanged from the outside. Cover this with a test
  that computes the same value before and after.
- `RosterService` — roster query, CSV export, birthdays within a window, and roster-scoped
  announcement hand-off to `AnnouncementService`.

### Permissions

`attendance.record` (`INSTRUCTOR`/`TA` = `O`), `attendance.view_all` (`INSTRUCTOR`/`TA` = `O`,
`ACADEMIC_ADMIN` = `F`), `attendance.view_own` (`STUDENT` = `O`), `attendance.edit` (`INSTRUCTOR` =
`O`, `ACADEMIC_ADMIN` = `F`), `attendance.reopen` (`ACADEMIC_ADMIN` = `F`),
`attendance.configure` (`ACADEMIC_ADMIN` = `F`), `attendance.report`, `attendance.self_check_in`
(`STUDENT` = `O`), `roster.view`, `roster.export`, `roster.announce`.

### Web

Teach hub: a session list with an attendance grid (search, bulk mark, fill-missing, close),
an attendance report tab, and roster export. Student: an attendance history page and a check-in
screen that accepts a code. Admin: the attendance policy editor and a cross-offering report.

### API

Student: `GET /attendance/mine`, `GET /offerings/{id}/attendance/mine`,
`POST /sessions/{id}/check-in`. Instructor: `GET /offerings/{id}/sessions`,
`GET /sessions/{id}/roster`, `POST /sessions/{id}/attendance` (bulk, carries `lock_version`),
`POST /sessions/{id}/attendance/fill-missing`, `POST /sessions/{id}/close`,
`GET /offerings/{id}/attendance/report`, `GET /offerings/{id}/roster`.

### Tests (`tests/Feature/Attendance/`, new suite `Attendance` wired into CI)

- `AttendanceMarkingTest` — mark, amend, excuse, fill-missing, close; a closed session rejects
  writes; reopen requires `attendance.reopen`.
- `AttendanceConcurrencyTest` — two concurrent bulk marks with the same `lock_version`; the second
  gets 409 and no data is lost. This is the test that justifies the column.
- `AttendanceSelfCheckInTest` — a valid code inside the window marks present; expired, over-limit,
  wrong-session, and replayed codes all fail; a non-enrolled student fails.
- `AttendancePolicyTest` — `min_percentage` and `late_grade_percentage` change the computed
  percentage; a disabled policy contributes nothing.
- `AttendanceGradebookParityTest` — the gradebook attendance component yields the same value through
  the new service as it did through the old one for a fixture offering.
- `AttendanceReportTest` — per-student, per-session, and aggregate figures; CSV export headers and
  row count.
- `AttendanceScopeTest` — an Instructor cannot mark another offering's roster (S0).
- `RosterTest` — roster, CSV export, birthdays window, roster announcement.
- `tests/Feature/Api/AttendanceApiTest` — student sees only their own history; instructor bulk mark
  round-trips including the 409 path.

### Acceptance

An instructor takes attendance for an in-person session with no Zoom meeting attached; a late
student self-checks-in with a code; the instructor excuses one absence; the gradebook attendance
component reflects all three; the registrar exports a term report.

---

## S4 — Course completion, criteria, and credentials (G-13, G-14, G-16) ✅ **Done**

### Schema

| Table | Purpose |
|---|---|
| `completion_criteria` | per course or offering: `kind` (`MIN_GRADE`/`MIN_ATTENDANCE`/`REQUIRED_ITEM`/`MIN_DISCUSSION`), `threshold`, `is_required` |
| `offering_closings` | the ceremony: `status` (`OPEN`/`GRADING_LOCKED`/`ANNOUNCED`/`CLOSED`), `grace_marks`, `locked_at`, `announced_at`, `closed_at`, actor columns |
| `completion_results` | per student per offering: `met_criteria` (json), `outcome` (`COMPLETED`/`NOT_COMPLETED`/`PENDING`), `evaluated_at` |
| `certificate_templates` | scoped to a course or global: `locale`, `title`, `body`, `background_path`, `signature_path` |
| `student_notes` | `offering_id`, `student_id`, `author_id`, `body`, `visibility` (`STAFF_ONLY`) |
| `module_student_assessments` | per week (or content group) per student: `rating`, `comment`, `assessed_by_id` |

`completion_criteria` must support the **conjunction** rule from the reference implementation: when
both a minimum grade and a minimum attendance criterion are defined, failing attendance forces a
non-completion outcome regardless of the grade earned. A criteria engine that only sums weighted
signals will quietly let a student with 90% marks and 40% attendance complete the course.

### Services

- `CompletionService::evaluate(offering)` runs criteria against grades, `AttendanceService`, item
  completions, and discussion grades, and writes `completion_results`. It is idempotent and
  re-runnable.
- `OfferingClosingService` — `lockGrading` (delegates to the existing `GradebookService::lockGrades`
  rather than duplicating it), `applyGraceMarks`, `announce` (through S2's dispatcher),
  `close` (issues credentials for `COMPLETED` students via the existing `CredentialService`).
- `CertificateTemplateService` — resolve by course then global then locale, render to PDF, store via
  `ObjectStorageService`, and keep the existing public `/verify/{token}` route as the verification
  surface. This is where SPIMS's credential verification is genuinely better than Khedma's and
  should not be replaced.
- **Render real PDFs.** `CredentialService` currently writes `credentials/{id}.html`. Introduce
  DomPDF (as the reference implementation uses) behind the same `file_url`, keeping the HTML path as
  the fallback when the renderer is unavailable so rule 7 still holds. A credential a registrar
  cannot hand to a student as a PDF is not finished, and the same shortcut in `ReceiptPdfService`
  should be fixed in the same pass.

### Permissions

`completion.view`, `completion.configure` (`ACADEMIC_ADMIN` = `F`), `offering.close`
(`ACADEMIC_ADMIN` = `F`, `INSTRUCTOR` = `O` for lock and announce only),
`certificate_templates.manage` (`ACADEMIC_ADMIN` = `F`), `student_notes.view`,
`student_notes.manage` (`INSTRUCTOR`/`TA` = `O`), `module_assessment.view`,
`module_assessment.manage` (`INSTRUCTOR`/`TA` = `O`).

### Web

Admin: a criteria editor per course, a closing wizard with consequence-aware copy in three locales,
and a certificate template editor with preview. Teach hub: a completion cohort view showing who
meets which criterion, plus student notes and per-week assessment on the roster drill-down.

### API

Student: `GET /me/credentials`, `GET /credentials/{id}/download`,
`GET /offerings/{id}/completion` (own result only). Instructor: `GET /offerings/{id}/completion`
(cohort), `POST /offerings/{id}/completion/evaluate`, `GET|POST /offerings/{id}/students/{id}/notes`,
`PUT /offerings/{id}/weeks/{id}/students/{id}/assessment`.

### Tests (`tests/Feature/Completion/`)

- `CompletionCriteriaTest` — each criterion kind independently decides an outcome; combined criteria
  require all `is_required` ones; re-running produces the same result.
- `ClosingWorkflowTest` — the status machine only advances forward; closing an offering with
  unlocked grades fails; grace marks change outcomes and are audited; announce dispatches once.
- `CertificateIssuanceTest` — closing issues exactly one credential per completing student, is
  idempotent on re-run, and the issued credential verifies at `/verify/{token}`.
- `CertificateTemplateTest` — course template beats global; locale fallback; PDF renders in Arabic
  with correct RTL.
- `StudentNotesPrivacyTest` — a student cannot read notes about themselves through web or API; a
  non-staff user gets 403.
- `tests/Feature/Api/CompletionApiTest` — a student sees only their own completion result.

### Acceptance

An academic admin defines "70% grade and 75% attendance", evaluates a cohort, applies two grace
marks, announces, and closes; certificates are issued in Arabic and verify publicly; a student sees
their own result and nobody else's.

---

## S5 — Assessment completion (G-17, G-18) ✅ **Done**

Small, high-value, and mostly additive columns on existing tables.

### Schema

- `assignments`: add `delivery_mode` (`ONLINE`/`OFFLINE`), `allow_resubmission`,
  `resubmission_deadline`, `late_penalty_percent`.
- `assignment_submissions`: add `received_at`, `received_by_id` (physical hand-in),
  `resubmission_of_id`, `attempt_no`, `superseded_at`.
- New `proctor_events`: `attempt_id`, `student_id`, `event_type`, `warning_number`, `details`,
  `created_at`.
- `assessment_attempts`: add `proctor_warnings`, `terminated_for_cheating`, `terminated_at`,
  `terminated_by_id`.
- New `assessment_result_announcements`: `assessment_id`, `announced_at`, `announced_by_id`.

### Services

- `AssignmentService` gains `markReceived`, `remindUnsubmitted` (through S2's dispatcher),
  `resubmit`, and a staff dashboard query (per-offering submission counts, ungraded counts,
  overdue counts).
- **Stop the silent overwrite. ✅ Done — commit [`1b02276`](https://github.com/RobsGeorge/spims-edu/commit/1b02276).**
  `submit()` used `updateOrCreate`, so a second submission destroyed the first and left the prior
  grade attached to content the instructor never saw. The patch keeps the unique
  `(assignment_id, student_id)` row canonical and archives prior states additively into
  `assignment_submission_versions`, bumping `attempt_no`, clearing the stale grade, and refusing the
  write when `allow_resubmission` is false. The remaining S5 work on top of it is the
  `resubmission_deadline` window and the staff-facing UI.
- `ProctorService` — records typed events, escalates `warning_number`, and terminates an attempt at
  a configurable threshold. Terminating writes an audit entry and does **not** delete the attempt.
  Wire the existing `focus-loss` endpoint into it so the counter finally does something.
- `AssessmentService::announceResults` — flips `released`, dispatches, records the announcement.
- Bulk offline grade entry for `delivery_mode = OFFLINE` assessments.

### Permissions

`assignments.dashboard`, `assignments.remind`, `assignments.mark_received` (`INSTRUCTOR`/`TA` = `O`),
`assessments.proctor` (`INSTRUCTOR` = `O`), `assessments.announce_results` (`INSTRUCTOR` = `O`,
`ACADEMIC_ADMIN` = `F`), `assessments.clear_termination` (`ACADEMIC_ADMIN` = `F`).

### Tests (suite `Assessment`)

- `AssignmentDashboardTest`, `AssignmentReminderTest` (only unsubmitted students, once per window),
  `AssignmentResubmissionTest` (allowed only inside the window, keeps history, late penalty applied),
  `AssignmentOfflineTest` (mark received without a file; offline assignments excluded from reminders).
- `AssignmentNoSilentOverwriteTest` — a second submission never mutates the first row; a graded
  submission cannot be overwritten when `allow_resubmission` is false; the full attempt history is
  retrievable. Write this test against the current behaviour first and watch it fail.
- `ProctorEscalationTest` — N focus-loss events raise warnings; the threshold terminates; a
  terminated attempt cannot be resumed or submitted; an admin can clear the flag and the attempt
  becomes gradeable.
- `ResultsAnnouncementTest` — announcing releases results and notifies once; re-announcing does not
  duplicate.
- Regression: `AssessmentEngineTest` stays green — this phase must not disturb the existing runner,
  banks, draw, or scoring rules, which are already ahead of the reference implementation.

---

## S6 — Student mobile API (G-01, part 2) ✅ **Done**

Ships the student surface in waves, mirroring Khedma's proven ordering from
`docs/mobile/student-feature-matrix.md`. Each wave is a PR; a wave only ships once the domain phase
behind it has landed.

| Wave | Depends on | Endpoints |
|---|---|---|
| **A — foundation** | S1, S2 | `/me`, `/me/preferences`, `/me/picture`, `/dashboard`, notifications, notification settings, announcements, branding |
| **B — academic read** | S3 | offerings, course player content, weeks and items, item completion, grades (released only), transcript, degree audit, attendance history, credentials |
| **C — academic write** | S5 | assignment list, detail, submit, resubmit; assessment list, start, save, submit, timer; discussions read and post |
| **D — SIS and money** | existing | catalog, application forms, apply, application status, enrollment, drop, withdraw, invoices, checkout, wallet, receipts |
| **E — engagement** | S7, S9 | team projects, feedback surveys, events, live quiz play |

Conventions, request and response shapes, and per-endpoint semantics are in
[`mobile-api-spec.md`](mobile-api-spec.md).

### Rules

- Controllers under `App\Http\Controllers\Api\V1` are thin: validate, delegate to the same service
  the web controller uses, return a resource. If a wave needs behaviour that does not exist in a
  service, it goes into the service — never into the controller.
- Every endpoint asserts the same permission key as its web counterpart, with the resource passed
  (S0).
- File uploads (`/me/picture`, assignment submit, project deliverables) accept multipart and route
  through `ObjectStorageService`, with the same size and MIME rules as web.
- Money is serialized as `{ "minor_units": 15000, "currency": "EGP", "formatted": "EGP 150.00" }`.
  Never a float, never a bare string.

### Tests (suite `Api`)

One test class per wave, plus:

- `StudentApiScopeTest` — a student cannot read another student's grades, attendance, submissions,
  invoices, or credentials by guessing a ULID. Parameterized across every student endpoint that
  takes an id, so new endpoints must be added to the list.
- `StudentApiParityTest` — for a fixture student, the API payload for grades, attendance, and
  completion matches what the web controller renders, asserted against the service output. This is
  the guard against a logic fork.
- `ApiPaginationTest`, `ApiUploadTest`, `ApiLocaleTest` extended per wave.

### Acceptance

A student completes, on a phone in Arabic, the full path: log in, read an announcement, watch a
week item, submit an assignment, sit a timed assessment, check attendance, view a grade, and pay an
invoice.

---

## S7 — Team projects (G-05) ✅ **Done**

The largest new subsystem. Port the model, not the Khedma table names, and align it to
`course_offerings` rather than Khedma's `course`.

### Schema

| Table | Purpose |
|---|---|
| `project_assessments` | the staff-authored container on an offering: title, `component_id` for gradebook sync, team size min and max, `join_opens_at`, `join_closes_at`, `allow_leave_once`, `status` |
| `projects` | one team: `project_assessment_id`, `name`, `status`, `workspace_url` |
| `project_memberships` | `project_id`, `student_id`, `role`, `joined_at`, `left_at` |
| `project_membership_events` | audit of join, leave, move, and merge |
| `project_phases` | ordered milestones with dates |
| `project_deliverables` | what each phase requires: `kind` (`FILE`/`LINK`/`TEXT`), `max_files`, `max_file_mb`, `due_at` |
| `project_deliverable_submissions` | per team per deliverable, with `reviewed_at`, `review_status`, `reviewer_id` |
| `project_submission_files` | individual files on a submission |
| `project_change_requests` | student-raised team changes: `kind`, `reason`, `status`, decision columns |
| `project_grade_criteria` | rubric rows with weights, team-level or student-level |
| `project_grades` | team and per-student scores against criteria, plus an announced flag |
| `project_peer_evaluations` | open and close window, per-rater per-ratee scores and comments |

### Services

`ProjectAssessmentService` (author, publish, lock), `ProjectTeamService` (join inside the window,
leave once with immediate reassignment, staff move and merge, seating), `ProjectDeliverableService`
(submit, replace, delete a file, staff review), `ProjectGradingService` (criteria, team score,
per-student override, scale, announce, push into the gradebook component),
`PeerEvaluationService` (open, submit, close, aggregate for staff review).

The rules worth copying exactly, because they are the ones Khedma iterated on: joining is only
possible inside the window; a student may leave exactly once and is immediately eligible to rejoin
elsewhere; team grades propagate to members unless a per-student override exists; peer evaluation is
closed before grades are announced.

**Peer evaluation is informational and must never write a grade.** This is a deliberate design
decision in the reference implementation, not an omission: `ProjectPeerEvaluationService` actively
guards against mutating `project_member_grades`, and surfaces ratings to staff as anonymous
aggregates grouped by rater team. Peer scores inform an instructor's per-student override; they do
not compute one. Build it the same way and assert the invariant in a test, because the obvious
implementation — deriving a multiplier from peer scores — lets students grade each other and is
very hard to walk back once results have been announced.

Two related rules from the same subsystem: grading operates in one of two modes (`rubric` or
`deliverables`) and in deliverable mode the deliverable points must sum to the assessment maximum;
and announcing results is what pushes scores into the gradebook, via a `project`-type component.

### Permissions

`projects.view` (`STUDENT` = `O`), `projects.join` (`STUDENT` = `O`), `projects.manage`
(`INSTRUCTOR`/`TA` = `O`), `projects.grade` (`INSTRUCTOR` = `O`, `TA` = `O`),
`projects.announce` (`INSTRUCTOR` = `O`), `projects.peer_eval` (`STUDENT` = `O`).

### API

Student: `GET /offerings/{id}/project-assessments`, `GET /projects/{id}`,
`POST /project-assessments/{id}/join`, `POST /project-assessments/{id}/leave`,
`POST /projects/{id}/deliverables/{id}/submit`, `DELETE /projects/{id}/submission-files/{id}`,
`GET /projects/{id}/peer-evaluations/pending`, `POST /projects/{id}/peer-evaluations`.
Instructor: assessment CRUD, team move and merge, submission review, criteria, team and student
grading, announce, CSV export.

### Tests (new suite `Projects`)

- `TeamFormationTest` — join before the window opens fails; join at capacity fails; leave once
  succeeds and a second leave fails; leaving frees a seat immediately.
- `DeliverableSubmissionTest` — file count and size limits; link and text kinds; replacing a file;
  deleting a file the team does not own fails; submitting after the due date is flagged late.
- `ProjectGradingTest` — criteria weights sum correctly; a per-student override beats the team
  score; the gradebook component receives the result; announce is idempotent.
- `PeerEvaluationTest` — a student cannot rate themselves, cannot rate outside their team, cannot
  submit twice, and cannot submit after close; aggregates are anonymous to staff.
- `PeerEvaluationDoesNotGradeTest` — submitting peer ratings leaves `project_member_grades`
  byte-for-byte unchanged, and announcing results ignores peer scores entirely. This is an
  invariant, not a feature test.
- `ProjectChangeRequestTest` — raise, approve, reject; approval applies the change atomically.
- `ProjectScopeTest` — cross-offering and cross-team access denied on every route (S0).
- `tests/Feature/Api/ProjectsApiTest` — the student surface end to end.

---

## S8 — Instructor mobile API (G-01, part 3) ✅

The instructor app is the reason S0 had to come first. Every endpoint here is a mutation on data
belonging to an offering the actor must be staffed on.

Surface (full detail in [`mobile-api-spec.md`](mobile-api-spec.md) §Instructor):

- **Teaching context** — `GET /teach/offerings`, `GET /teach/offerings/{id}` (tabs summary).
- **Attendance** — session list, roster, bulk mark with `lock_version`, fill-missing, close, report.
- **Grading** — gradebook grid read, component list, submission grading, assessment answer override,
  attempt list with status, submit and lock with an explicit confirmation token so a mis-tap cannot
  lock a term.
- **Roster** — students, birthdays, notes, per-week assessment.
- **Announcements** — draft, publish to the offering.
- **Assignments** — dashboard counts, mark received, remind unsubmitted.
- **Projects** — team list, submission review, grading, announce.
- **Live sessions** — schedule read, join link, attendance import trigger.

### Rules

- Every route sits behind `permission.scoped`, and the token must carry the `role:INSTRUCTOR` or
  `role:TA` ability.
- Destructive or irreversible actions (`gradebook.lock`, `offering.close`, `projects.announce`)
  require a `confirmation` field echoing a server-issued token from a preceding `GET`. This is the
  API equivalent of the consequence-aware confirm dialogs SPIMS built in phase D4, and it is what
  keeps "calm under stakes" true on a 5-inch screen.
- TA restrictions are enforced server-side, not by hiding buttons: a TA token calling
  `gradebook/lock` receives 403.

### Tests (suite `Api`)

- `InstructorApiScopeTest` — for every instructor endpoint, an instructor staffed on offering A is
  denied on offering B. Parameterized over the route list so a new route without a scope test fails
  the build.
- `InstructorApiRoleTest` — TA is denied lock, reopen, and announce; Instructor is allowed lock but
  denied reopen; Academic Admin is allowed reopen.
- `InstructorConfirmationTest` — irreversible actions without a valid confirmation token return 422;
  a replayed token is rejected.
- `InstructorAttendanceApiTest` — bulk mark, stale `lock_version` conflict, fill-missing, close.
- `InstructorGradingApiTest` — grade a submission, override an answer score, submit, lock; a locked
  gradebook rejects further grading.

### Acceptance

An instructor takes attendance, grades three submissions, publishes an announcement, and locks the
gradebook from a phone; a TA performs the same session except locking, which is refused.

---

## S9 — Realtime, live quiz, and events (G-06, G-08, G-19) ✅ **Done** (domain; Reverb still optional)

Last because it introduces infrastructure SPIMS does not have.

### Realtime transport

Add Laravel Reverb, configure `config/broadcasting.php`, and define private channels in
`routes/channels.php` authorized by the same scope resolver from S0. Reverb runs as a separate
process; document it in `docs/vps-setup.md` and add it to the deploy workflow.

**Every realtime feature must degrade to polling.** If Reverb is unavailable, the live quiz falls
back to a 2-second poll and events fall back to page refresh. This keeps CI and local development
free of a websocket dependency and honours rule 7's spirit.

### Live quiz schema

`live_quizzes`, `live_quiz_questions`, `live_quiz_options`, `live_quiz_sessions` (with a join code
and a state machine: `LOBBY`/`QUESTION_OPEN`/`QUESTION_CLOSED`/`RESULTS`/`ENDED`),
`live_quiz_participants`, `live_quiz_answers` (with `answered_at` for speed scoring).

`LiveQuizHostService` (start, launch question, close, show results, end) and `LiveQuizPlayService`
(join by code, answer once per question, score by correctness and speed). The server is
authoritative on timing — a client-supplied timestamp is never trusted, exactly as with the exam
timer.

### Events schema

`events`, `event_reservations`, `event_reservation_exceptions`, `event_check_ins`, `event_admins`.
`EventService` covers publish, cancel, capacity, eligibility (by program, offering, or role), and
reservation with waitlist. `EventCheckInService` issues a signed QR payload per reservation and
verifies it once.

### Permissions

`live_quiz.play` (`STUDENT` = `O`), `live_quiz.host` (`INSTRUCTOR`/`TA` = `O`),
`live_quiz.manage` (`INSTRUCTOR` = `O`, `ACADEMIC_ADMIN` = `F`), `events.view`, `events.reserve`,
`events.admin` (`ADMINISTRATIVE_ADMIN` = `F`), `events.check_in`.

### API

Student: `POST /live-quiz/join`, `GET /live-quiz/sessions/{id}`,
`POST /live-quiz/sessions/{id}/questions/{id}/answer`; `GET /events`, `GET /events/{id}`,
`POST /events/{id}/reserve`, `POST /events/{id}/cancel`, `GET /events/mine`.
Instructor: host lifecycle; admin: event CRUD and the check-in verify endpoint.

### Tests (new suites `LiveQuiz`, `Events`)

- `LiveQuizLifecycleTest` — the session state machine rejects out-of-order transitions.
- `LiveQuizScoringTest` — one answer per participant per question; late answers score zero;
  speed bonus is deterministic on fixed timestamps; a non-participant cannot answer.
- `LiveQuizFallbackTest` — with broadcasting disabled, the poll endpoint returns the same state.
- `EventReservationTest` — capacity, waitlist promotion on cancel, double reservation rejected,
  eligibility exceptions honoured.
- `EventCheckInTest` — a QR payload verifies once, a replay fails, a forged signature fails, and a
  check-in for a cancelled reservation fails.

---

## 11. Testing strategy

### Suite layout

Add five suites to `phpunit.xml` and to the CI gate in `.github/workflows/ci.yml`, in this order
(cheapest and most foundational first, so a break surfaces early):

```
Unit → Database → Auth → Audit → Smoke → Admin → Api → Academics → Offerings
     → Admissions → Enrollment → Finance → Assessment → Attendance → Completion
     → Projects → Communications → Live → LiveQuiz → Events → Credentials
     → Hardening → Portal → Rbac → Integrations
```

`Auth` moves earlier than it is today and becomes the gate for S0: if resource scoping regresses,
nothing downstream should run.

### Non-negotiable test classes

Four suites function as invariants and must be green on every PR, not just the PR that adds them:

1. **Scope suite** (`tests/Feature/Auth/ResourceScopeTest` and the per-phase `*ScopeTest`) — the
   equivalent of Khedma's tenant-isolation suite. Cross-offering access must fail everywhere.
2. **API parity suite** (`StudentApiParityTest`, `InstructorApiParityTest`) — API and web must agree,
   because they share services. A divergence means logic leaked into a controller.
3. **Money suite** — extend `MoneyTest`; assert no float ever reaches a money column, and that
   every API money payload is the three-field object.
4. **Audit suite** — extend `AuditLogTest`; every new service mutation writes an audit row.
   Parameterize over the service method list so a new unaudited mutation fails the build.

### Coverage expectations per phase

Each phase PR carries, at minimum: a happy-path feature test per service method; a negative test per
permission key introduced; a scope test per new resource-bound route; a localization assertion that
new user-facing strings resolve in `ar`, `en`, and `fr`; and an audit assertion per mutation.

### Data and fixtures

Build one shared `AcademicScenarioSeeder` for tests: a program, two offerings, two instructors
staffed on one each, a TA, four students enrolled across them, and one student enrolled in neither.
The last one is the fixture that makes scope tests meaningful, and it is the fixture teams usually
forget.

### Manual verification per phase

Automated tests do not cover RTL rendering or the feel of a confirmation dialog. Each phase closes
with a short manual pass: the primary flow in Arabic on a narrow viewport, the same flow in French,
and one deliberate mistake (wrong code, stale lock, missing permission) to confirm the error copy is
comprehensible.

---

## 12. Sequencing summary

| Phase | Closes | Depends on | Nature of change |
|---|---|---|---|
| S0 | G-02 | — | Invasive to authorization; touches every admin route and service |
| S1 | G-01a | S0 | New surface only; no domain change |
| S2 | G-09…G-12 | S1 | New tables plus an additive extension of `announcements` |
| S3 | G-03, G-04, G-15 | S0 | New tables; one gradebook integration point changes |
| S4 | G-13, G-14, G-16 | S3 | New tables; reuses existing gradebook and credential services |
| S5 | G-17, G-18 | S2 | Mostly additive columns; low risk |
| S6 | G-01b | S1 + phase per wave | New surface only |
| S7 | G-05 | S0, S2 | Largest new subsystem; self-contained |
| S8 | G-01c | S0, S3, S5, S7 | New surface; highest authorization risk |
| S9 | G-06, G-08, G-19 | S1, S2 | New infrastructure (Reverb); self-contained features |

**Highest risk:** S0, because it deliberately breaks anything that relied on unscoped access, and S8,
because it exposes mutations to a mobile client. **Highest value:** S3 and S6 — attendance is the
missing SIS spine, and the student API is the stated goal. **Most self-contained:** S7 and S9, which
can be deferred without blocking anything else.

## 13. Definition of done

SPIMS has full SIS + LMS coverage with a mobile API when:

1. Every gap G-01 … G-19 is closed, or explicitly re-parked with a recorded owner decision.
2. `O` in the permission matrix is enforced, and the scope suite proves it on every route.
3. A student completes the full academic year on a phone in Arabic: apply, enroll, pay, learn,
   submit, sit an exam, attend, see grades, and receive a verified certificate.
4. An instructor runs an offering from a phone: content, attendance, grading, announcements,
   projects, and grade lock — and a TA is correctly refused the lock.
5. Attendance is a reportable academic record independent of Zoom, with excuses and exports.
6. Every outbound message is logged and reportable.
7. `docs/api/openapi.yaml` covers every `/api/v1` route, and the coverage test enforces it.
8. The CI gate is green across all suites, including the four invariant suites.
