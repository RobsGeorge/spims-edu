# Academic gap analysis — Khedma academic hub vs SPIMS

**Reference implementation compared against:** AvaPachomius-Khoddam, `main` @ `39e411f`
**Analysed here:** this repository, `main` @ `d764d1e`
**Method:** static comparison of routes, migrations, models, services, permission catalogs, and
test suites in both repositories. Absences were confirmed by keyword sweeps across `app`, `routes`,
`config`, `database`, and `resources`, and are stated explicitly rather than inferred.

> Written before S0 and S1 landed, and kept as the baseline record. It describes this repository as
> it stood at `d764d1e`, so the two defects it names in §4.2 and §4.10 — and the missing `/api/v1`
> in §4.1 — are now fixed. See [`README.md`](README.md) for current phase status.

---

## 1. What is being compared

The Khedma **Academic Hub** is not a page; it is a permission-filtered link set built by
`NavigationHub::academicLinks()` in `app/Support/NavigationHub.php` and rendered by
`resources/views/hubs/academic.blade.php`. It groups eighteen feature areas into four sections —
`learning`, `assessment`, `people`, `community` — and each link is gated twice: by a **permission
key** (139 keys in `config/permissions.php`) and by a per-church **capability** (14 switches in
`config/capabilities.php`).

SPIMS has a structurally similar idea in `app/Support/NavigationHub.php` and
`HubController::academic()`, but its hubs (`learning`, `academic`, `admin`, `finance`) organize a
much smaller surface, and it has no capability layer — a permission key is the only gate.

This report walks the Khedma academic areas in hub order, then covers the cross-cutting concerns
(API, authorization, notifications, realtime) that are not hub links but determine whether the
features can be delivered to a mobile client at all.

---

## 2. Severity scale

| Severity | Meaning |
|---|---|
| **Absent** | No table, model, route, or service exists in SPIMS |
| **Thin** | A partial implementation exists but omits the workflow that makes the feature useful |
| **Parity** | Comparable capability, possibly a different shape |
| **SPIMS ahead** | SPIMS implements this better, or implements it where Khedma does not |

---

## 3. Gap matrix

| # | Area | Khedma | SPIMS today | Severity |
|---|---|---|---|---|
| 1 | Curriculum structure | Reusable `modules` catalog attached to many courses, lectures, materials, media download, module lifecycle | `weeks` + `content_items` per offering, gated | **Thin** |
| 2 | Class sessions | `session` entity, CRUD, close-attendance, per-session student notification | `live_sessions` (Zoom) + `session_recurrences` only | **Thin** |
| 3 | Attendance | First-class record, policy table, excuses, optimistic lock, guardian check-in, 6 report routes | Zoom import + manual override, bound to a live session | **Thin (near-absent)** |
| 4 | Assignments | CRUD, dashboard, grading, reminders, mark-received, offline delivery, resubmission | Create from content item, submit, grade | **Thin** |
| 5 | Team projects | 11 tables, ~35 routes, teams, phases, deliverables, peer evaluation, change requests | — | **Absent** |
| 6 | Exams | Builder, schedule, attempt runner, proctor event log, offline delivery, results announcement | Question banks, random draw, multi-attempt, AI grading, focus-loss counter | **Parity** (differing strengths) |
| 7 | Gradebook | Categories, items, score grid, CSV export, grace marks, course closing | Weighted components, submit/lock/reopen, GPA, academic records | **Parity** |
| 8 | Graduation criteria | Per-course criteria engine, cohort view, CSV export | — (program degree audit exists, per-course does not) | **Absent** |
| 9 | Certificates | Per-course editable templates, PDF, UUID download | `credentials` + public `/verify/{token}`, but HTML artifacts and manual issuance | **Thin** |
| 10 | Announcements | Revisions, deliveries, targeting, publish, email resend, WhatsApp, banner, directory | `announcements` table, create-only from Teach hub | **Thin** |
| 11 | Communications report | `communication_logs`, report, CSV export, open tracking | — | **Absent** |
| 12 | Email templates | Per-course editable + preview, role-assignment, graduation | — | **Absent** |
| 13 | Student roster | Roster, CSV export, birthday announcement, birthdays feed | Read-only roster tab in Teach hub | **Thin** |
| 14 | Feedback surveys | Surveys, questions, submissions, anonymity + identity-reveal approval | — | **Absent** |
| 15 | Live quiz | 6 tables, host console, play flow, Reverb realtime | — | **Absent** |
| 16 | Events | 6 tables, capacity, eligibility, reservations, QR check-in | — | **Absent** |
| 17 | Module student assessment + instructor notes | Per-module per-student assessment, private notes | — | **Absent** |
| 18 | Course applications | Multi-step form builder, apply, status, review | Single-form builder, apply, review, round-robin | **Parity** |
| 19 | Notifications | Preferences, reminders, WhatsApp deliveries, multi-channel | `notifications` table, in-app + mail; the one `notify_email` toggle is never read | **Thin + defect** |
| 20 | **Mobile API** | 57 `/api/v1` endpoints, Sanctum, documented waves | **None** | **Absent** |
| 21 | **Resource-scoped authz** | `CoursePermissionResolver::canInCourse()` | `$resource` accepted and ignored | **Defect** |
| 22 | Realtime | Laravel Reverb, `routes/channels.php` in use | Broadcasting config present, unused | **Absent** |
| — | Programs, semesters, offerings | — | Full | **SPIMS ahead** |
| — | Admissions + enrollment engine | Course applications only | Windows, waitlist, holds, drop/withdraw refunds | **SPIMS ahead** |
| — | Transcript, GPA, degree audit | — | Full | **SPIMS ahead** |
| — | Finance | Church payroll/money-in only | Invoices, 4-bucket wallet, gateways, refunds, donations | **SPIMS ahead** |
| — | Discussions | — | Boards, threads, graded participation, moderation | **SPIMS ahead** |
| — | Question banks + AI grading | — | Banks, random draw, Gemini essay suggest | **SPIMS ahead** |

---

## 4. Findings in detail

### 4.1 Mobile API — absent, and the largest single gap

At `d764d1e`, [`routes/api.php`](../../routes/api.php) was 20 lines and contained one route: the Laravel default
`GET /api/user` behind `auth:sanctum`. There is no versioned prefix, no controller namespace under
`app/Http/Controllers/Api/V1`, and no API resources. The four classes under
`app/Http/Controllers/Api/` are webhook and upload endpoints registered in `routes/web.php`
(`api.branding`, `api.webhooks.payments`, `api.webhooks.zoom`, `api.uploads.store`), not a client
API. SPIMS is Blade-first and session-authenticated throughout.

Khedma, by contrast, exposes 57 routes under `/api/v1` in `routes/api.php`, authenticated with
Sanctum bearer tokens and layered with three middleware (`church.member`, `token.church`, and
`capability:*`). The student surface is planned and tracked in
`docs/mobile/student-feature-matrix.md`, which sequences delivery into waves A–E and records, per
feature, the web route, the permission key, the API status, and the client status.

Two consequences for the plan. First, everything mobile is greenfield here — token issuance,
response envelope, pagination, error shape, and localization all need deciding before the first
endpoint. Second, [`PARKING-LOT.md`](../../PARKING-LOT.md) listed "Full REST JSON API surface" and
"Mobile native apps" as deferred, so this work is a deliberate promotion out of the parking lot
rather than a resumption of planned work.

**Resolved for the foundation.** S1 built `/api/v1` with `login`, `logout`, `me`, and `branding`;
S6 (student) and S8 (instructor) endpoint surfaces are now on `main`.

### 4.2 Authorization is role-level, not resource-scoped — a live defect

[`app/Support/AuthorizeService.php`](../../app/Support/AuthorizeService.php) has the signature:

```php
public function authorize(?User $user, string $action, mixed $resource = null): void
```

At `d764d1e`, `$resource` was never read in the method body. A sweep of every `authorize(` call site
in `app/` showed that no caller passed a third argument. The permission matrix in
[`config/permissions.php`](../../config/permissions.php) distinguishes `F` (full), `R` (read), and
`O` (own) — for example
`'gradebook.lock' => ['INSTRUCTOR' => 'lock']` and `'assessments.manage' => ['INSTRUCTOR' => 'O']` —
but `levelFor()` collapses all of these to a boolean allow:

```php
if ($level === 'F' || $level === 'R' || str_contains($level, 'O')) {
    $allowed = true;
```

So `O` grants the same access as `F`. Scoping is enforced in exactly the places that added it by
hand: `TeachAccessService::assertCanTeachOffering()` for `/teach/*`, and
`OfferingAccessService` for the course player. It is **not** enforced on the `/admin/*` routes that
wrap the same services. `GradebookController::lock()` takes a route-model-bound `CourseOffering`,
calls `GradebookService::lockGrades()`, and the only guard on the path is
`->middleware('permission:gradebook.lock')` — a role check. Any Instructor can therefore lock,
submit, or reopen grades, add gradebook components, or grade submissions for **any** offering in
the school. The same pattern applies to `AssessmentAdminController`, `LiveSessionAdminController`,
and `DiscussionAdminController`.

This repository's own [portal design gap analysis](../portal-design-gap-analysis.md) already records
a related symptom — "Assignment detail and discussion board/thread readable by any authenticated
user who knows an ID (membership not enforced)" — so the class of problem was known, but the root
cause in `AuthorizeService` is not named there.

Khedma solves this with `App\Services\CoursePermissionResolver`, which answers
`canInCourse($user, $permission, $course)` and `canAnyInCourse(...)`, and the navigation hub itself
is built by asking that resolver rather than by inspecting roles.

This is listed as a gap rather than a feature because exposing an instructor mobile API over the
authorization model as it stood would widen a real vulnerability.

**Resolved by S0.** `config/permission_scopes.php` names the offering-scoped keys and the roles they
confine, and `App\Support\Scope\ResourceScopeResolver` answers the membership question. Scoped keys
now fail closed without a resource.

### 4.3 Attendance — present in name, absent in substance

SPIMS's `attendance_records` table (in `2026_07_28_100006_create_live_tables.php`) is:

```php
$table->foreignUlid('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
$table->foreignUlid('student_id')->constrained('users');
$table->string('status');
$table->unsignedInteger('minutes_attended')->default(0);
$table->string('source')->default('ZOOM_IMPORT');
$table->foreignUlid('overridden_by_id')->nullable()->constrained('users')->nullOnDelete();
```

Attendance can only exist against a Zoom-backed `live_sessions` row. `AttendanceService` exposes
import and override, and `GradebookService` consumes `offeringPercent()` for the attendance
gradebook component. That is the whole feature. [`PARKING-LOT.md`](../../PARKING-LOT.md)
additionally records that an "excused" state is deliberately out of scope because "spec uses
present/absent only".

Khedma models attendance as its own domain. `attendance` carries `user_id`, `session_id`,
`taken_by_id`, a status, a `permission_reason` (the excuse), and `attendance_time`; later migrations
add `lock_version` for optimistic concurrency control on concurrent marking, and `person_id` for
non-user attendees. `attendance_policy` holds the rules (`late_grade_percentage`, enablement), and
`session` gained `attendance_closed`, `session_start_time`, and `notify_students`. The routes cover
recording, viewing all, viewing by date, per-user history, per-user reports, an aggregate report, a
student's own history, bulk fill-missing, roster search, and a guardian check-in path.

For a *student information system*, attendance is a reportable academic record, not a side effect
of a video call. This is the most consequential SIS gap.

### 4.4 Team projects — absent

Khedma's project subsystem spans eleven tables introduced across seven migrations between
2026-08-22 and 2026-08-30: `project_assessments`, `projects`, `project_memberships`,
`project_phases`, `project_deliverables`, `project_change_requests`, plus grading, join-window and
seating, deliverable submissions, team grade criteria, gradebook sync, membership events, submission
review columns, deliverable grading, workspace provider, and peer evaluation. It exposes roughly
35 web routes covering team formation with a join window and one-time leave, deliverable submission
with multi-file uploads, staff review, team and per-student grading against criteria, a grade scale,
result announcement, peer evaluation windows, member moves and team merges, change requests with
approve/reject, and CSV export.

Nothing in SPIMS corresponds to this. A sweep for `project`, `deliverable`, `peer_rating`, and
`team` finds only incidental matches. It is the largest single feature to port.

### 4.5 Live quiz — absent

Khedma has a synchronous, Kahoot-style quiz engine distinct from asynchronous exams:
`live_quizzes`, `live_quiz_questions`, `live_quiz_options`, `live_quiz_sessions`,
`live_quiz_participants`, `live_quiz_answers`. The host flow is start → lobby → launch question →
control → show results → end, with a `present` projector view; players join by code, wait in a
lobby, and answer. It is gated by `capability:live_quiz` and the permission keys `live_quiz.play`,
`live_quiz.host`, and `live_quiz.manage`, and it depends on Laravel Reverb for realtime.

SPIMS has no equivalent, and no realtime transport in use — `config/broadcasting.php` exists as
Laravel scaffolding, `routes/channels.php` is the default, and there is no Reverb dependency. Its
parking lot lists "WebSockets / SSE for notifications & discussions (v1 = poll/reload)" as deferred.
Porting live quiz therefore means introducing a realtime transport, which is why the plan isolates
it into a late phase with a polling fallback.

### 4.6 Feedback surveys — absent

Khedma's `feedback_surveys`, `feedback_questions`, `feedback_submissions`, and `feedback_answers`
support staff survey authoring with publish and close lifecycle, student submission, and reporting
sliced by question, by submission, and by student. Its distinguishing feature is the anonymity
model: submissions are anonymous by default, and a staff member must file an identity-reveal request
(`feedback_identity_reveal_requests`, added 2026-08-12) which a superadmin approves or denies via
`superadmin.feedback-reveal.*`. The permission keys are `feedback.view`, `feedback.manage`,
`feedback.report`, `feedback.identity.request`, and `feedback.identity.reveal`.

SPIMS has no survey engine. The 20 files matching "feedback" are all grading feedback text on
`AssignmentSubmission`, `AttemptAnswer`, and `DiscussionGrade`, plus Blade form validation messages.

Course-evaluation surveys are standard SIS functionality, and the reveal-approval workflow is the
part most often built wrong, so it is worth porting the design rather than reinventing it.

### 4.7 Events and reservations — absent

Khedma's events module (`2026_06_21_000001_create_events_module_tables.php`) creates `events`,
`event_reservations`, `event_reservation_exceptions`, `event_check_ins`, `event_admins`, and
`event_module_test_runs`. Admins create, publish, and cancel events, view reservations, manage
eligibility exceptions, and run a QR check-in console with a signed verify route
(`events.check-in.verify`). Students browse, view, reserve, cancel, and list their reservations.

SPIMS has nothing here — the 13 files matching "event" are Laravel's own event system and the Zoom
webhook. For a school running retreats, lectures, and ceremonies alongside coursework, this is a
real functional hole, though it is the least entangled with the academic core and can land late.

### 4.8 Announcements — thin

SPIMS's `announcements` table is five columns: `offering_id`, `author_id`, `title`, `body`,
`created_at`. The only write path is `TeachController::storeAnnouncement()`, and there is no read
route for students, no edit, no delete, and no publish step. This repo's
[portal design gap analysis](../portal-design-gap-analysis.md) lists
Announcements UI as "Model + table only; no controller/views" — the Teach hub tab was added later,
but the student side remains unbuilt.

Khedma has `announcements`, `announcement_revisions`, `announcement_deliveries`, and
`announcement_target_users`, with a manage surface covering create, edit, publish, email resend,
a WhatsApp hand-off with a sent-marker, and a recipient directory; and a student surface with an
index, a detail view, and a dismissible banner. Delivery is recorded per recipient, which is what
makes the communications report in §4.9 possible. The permission keys separate `announcement.view`,
`announcement.manage`, and `announcement.publish`.

### 4.9 Communications logging and email templates — absent

Khedma records every outbound message in `communication_logs` (added 2026-07-24) and exposes
`communications.report`, a CSV export, and a `communications.track-open` pixel route, behind the
`communications.report` permission. Alongside it, staff can edit message copy without a deploy:
`course_email_templates` per course with a preview endpoint, `role_assignment_email_templates`, and
`course_graduation_email_templates`. A `user.communication_locale` column added in the same
migration lets each recipient be mailed in their own language.

SPIMS has `TransactionalMailer` and per-domain notification sends, but no delivery log, no
reporting, no open tracking, and no editable templates — a sweep for `email_template` returns
nothing. For a school that must show it notified a student, the absence of a delivery log is an
operational risk, not only a missing feature.

### 4.10 Notification preferences and channels — thin

SPIMS's `notifications` table has a `channel` column defaulting to `IN_APP` and a `read_at`, and
`2026_07_29_150100_add_notify_email_to_users_table.php` adds a single boolean `notify_email` to
users. That is the whole preference model.

Khedma's notifications hub (`2026_07_15_000001`) creates `user_notifications`,
`user_notification_preferences`, `user_notification_reminders`, and
`notification_whatsapp_deliveries`, and exposes `GET/PUT /api/v1/notification-settings` to mobile.
Per-event, per-channel preferences and user-scheduled reminders are the parts SPIMS lacks; WhatsApp
is explicitly v2 in [`PARKING-LOT.md`](../../PARKING-LOT.md) and should stay there.

**The one preference SPIMS does have is not honoured.** `NotificationService` never reads
`users.notify_email` before sending: when called with the default `alsoEmail = true` it always
writes an `EMAIL` channel row and invokes `TransactionalMailer`. The settings UI presents a toggle
that changes nothing. That is a defect rather than a gap, and it is worth fixing in S2 alongside the
real preference model rather than leaving a control that lies to the user.

### 4.11 Graduation criteria and course closing — absent

SPIMS grades at two levels: an offering's gradebook produces `final_percent`, `final_letter`, and
`final_gpa_points` on the enrollment, and locking posts an `academic_record` which feeds
`program_requirement_fulfillments` and a cached program GPA. That is a genuine SIS transcript spine
and Khedma has no equivalent.

What SPIMS lacks is the *per-course completion* layer:
`2026_07_17_000001_create_course_graduation_closing_tables.php` creates `course_graduations`,
`course_graduation_students`, `course_certificate_templates`, `course_certificates`, and
`course_graduation_email_templates`. The closing workflow — lock grading, apply grace marks,
announce, close — is a distinct staff ceremony from locking a gradebook, and it is what produces
certificates. SPIMS's `credentials` are issued manually by an administrator through
`CredentialAdminController`; there is no criteria evaluation, no cohort view, no grace-mark step,
and no per-course certificate template editor.

Two further details matter for the plan. SPIMS's issued artifact is **HTML, not PDF** —
`CredentialService` writes `credentials/{id}.html` to `file_url`, mirroring the same shortcut
`ReceiptPdfService` takes for receipts. Khedma renders real PDFs through DomPDF. And Khedma's
graduation rule is stricter than a grade threshold: a course must define **both**
`passing_percentage` and `min_attendance_percentage` before `hasGraduationCriteria()` is true, and
failing the attendance threshold forces an F regardless of the grade earned. Any criteria engine
built for SPIMS should support that conjunction, because it is the rule that makes attendance
consequential rather than decorative.

### 4.12 Curriculum, sessions, roster, and the smaller thin areas

**Curriculum.** SPIMS models content as `weeks` → `content_items` scoped to one offering, with
gating and a course player. Khedma models a reusable `modules` catalog joined to courses through
`course_module`, with `module_content`, lectures, and lecture materials, plus a module lifecycle
(`curriculum.end-module`) and a signed media download route. The practical gap is reuse: a SPIMS
week cannot be shared between two offerings without copying, which matters for a school that runs
the same course every term. `OfferingController` does have a clone path, so this is a modelling
preference rather than a blocker, and the plan treats it as low priority.

**Sessions.** SPIMS's only session concept is a Zoom `live_sessions` row. Khedma's `session` is a
scheduled class meeting independent of delivery mode, with attendance close, a `notify_students`
toggle, `session_notification_targets`, and a "notify next session" action. Porting attendance
(§4.3) requires porting a session entity that is not Zoom-bound.

**Roster.** SPIMS renders a roster in the Teach hub by querying `Enrollment` with `student`, which
covers the read case. Missing are CSV export, the birthdays feed (`students.birthdays`, absent
entirely — a sweep for "birthday" returns zero files), the roster-scoped announcement action, and
per-student instructor notes (`student_notes.*` permissions, `StudentInstructorNoteController`).

Birthdays are not merely an unbuilt query: SPIMS's `users` table has **no date-of-birth column at
all**, so the feature needs a schema addition and a backfill path before any endpoint can exist.

**Assignments.** SPIMS supports create-from-content-item, submit, and grade. Khedma adds a staff
dashboard, per-assignment status, `mark-received` for physical hand-ins, `remind-unsubmitted`,
offline delivery (`2026_07_24_000001_add_offline_delivery_to_assignments.php`), and a student
resubmission route.

There is a sharper problem inside SPIMS's submit path than a missing feature.
`AssignmentService::submit()` uses `updateOrCreate` keyed on assignment and student, so a second
submission **silently overwrites the first** — the earlier file URL, text body, timestamp, and late
flag are gone, with no history row and no audit of what was replaced. This is not a resubmission
workflow; it is unversioned destructive overwrite that happens to look like one. A student who
re-uploads after an instructor has already graded destroys the graded artifact. S5 replaces it with
an explicit, windowed, history-preserving resubmission rather than adding a route beside it.

**Exams.** This is closer to parity than expected, and in several respects **SPIMS is ahead**. Its
`assessments` table supports question banks with random draw (`draw_from_bank_id`,
`questions_to_draw`), multiple attempts with a `scoring_rule` (`HIGHEST`/`LATEST`/`AVERAGE`), option
shuffling, per-attempt `exam_snapshot` and `question_ids` for reproducibility,
`results_visibility`, and AI-suggested scores with rationale on `attempt_answers`. Khedma has none
of that. The question-type gap runs the same direction and is wider than it first appears: SPIMS's
`QuestionType` enum has **ten** members (`MCQ_SINGLE`, `MCQ_MULTI`, `TRUE_FALSE`, `SHORT_ANSWER`,
`ESSAY`, `MATCHING`, `FILL_BLANK`, `NUMERIC`, `ORDERING`, `FILE_UPLOAD`), all auto-graded by
`ObjectiveGrader` except essay and file upload; Khedma supports **three** (`mcq`, `true_false`,
`essay`).

What Khedma has that SPIMS does
not is integrity *enforcement*: `exam_proctor_events` logs typed events with escalating
`warning_number`, and `exam_attempts` carries `proctor_warnings`, `terminated_for_cheating`, and
`terminated_at`, with staff routes to announce results, clear a cheater flag, recompute total
points, and enter offline grades in bulk. SPIMS counts `focus_loss_count` but never acts on it, and
its parking lot defers "hard exam proctoring (beyond soft integrity)". The gap here is small and
targeted.

---

## 5. Where SPIMS is ahead

Recording this matters, because the plan must not regress it and because "full SIS" is closer than
the headline gap count suggests.

- **Academic structure.** `programs`, `program_courses`, `course_prerequisites`, `academic_years`,
  `semesters`, `course_offerings`, `offering_staff`. Khedma has a flat course/service model.
- **Admissions.** Dynamic `application_forms` with typed fields, a review queue with round-robin
  assignment, reassignment, prefill, and matriculation into `student_programs`.
- **Enrollment.** Registration windows, waitlist with promotion, financial holds, drop versus
  withdraw with distinct refund treatment, and schedule-conflict warnings.
- **Transcript and GPA.** `academic_records`, `program_requirement_fulfillments`, cached program
  GPA, degree audit, and a transcript view.
- **Credentials.** Issued credentials with a public `GET /verify/{token}` QR verification page and
  serials of the form `SPIMS-CRED-{YEAR}-{NNNNN}`. Khedma has **no** unauthenticated verification
  route — its certificate download is auth-gated and the UUID is only embedded in the PDF body, so
  this is a genuine SPIMS advantage that must not be regressed. (The artifact itself is still HTML
  rather than PDF; see §4.11.)
- **Finance.** Invoices and lines, a four-bucket wallet (EGP/USD money and points), PayPal, Paymob
  and Cashier routing, split payments, refunds, donations, and receipts — all in integer minor
  units behind a `Currency` enum.
- **Discussions.** Boards, threads with participation thresholds (`participation_min_words`,
  `participation_min_posts`, `participation_min_replies`), auto-scored graded participation,
  moderation, and a gradebook component.
- **Assessment breadth.** Ten question types against Khedma's three, with `ObjectiveGrader`
  auto-scoring numeric tolerance, matching with partial credit, ordering, and fill-in-the-blank
  accepted-answer sets. Khedma auto-grades only MCQ and true/false.
- **Trilingual.** ar/en/fr with an AI translation service and a human verification step.

---

## 6. Consolidated gap register

Identifiers are used by [`implementation-plan.md`](implementation-plan.md).

| ID | Gap | Severity | Phase |
|---|---|---|---|
| G-01 | No `/api/v1` mobile API | Absent | S1, S6, S8 |
| G-02 | `AuthorizeService` ignores `$resource`; `/admin/*` unscoped | Defect | S0 |
| G-03 | Attendance not an SIS record; no session entity independent of Zoom | Thin | S3 |
| G-04 | No student-facing attendance history, reports, or exports | Absent | S3 |
| G-05 | Team projects subsystem | Absent | S7 |
| G-06 | Live quiz engine | Absent | S9 |
| G-07 | Feedback surveys + identity-reveal approval | Absent | S5 |
| G-08 | Events, reservations, QR check-in | Absent | S9 |
| G-09 | Announcements: publish workflow, targeting, delivery, student inbox | Thin | S2 |
| G-10 | Communications log, report, export, open tracking | Absent | S2 |
| G-11 | Staff-editable email templates + preview | Absent | S2 |
| G-12 | Notification preferences and reminders; `notify_email` toggle not honoured | Thin + defect | S2 |
| G-13 | Per-course graduation criteria + closing workflow | Absent | S4 |
| G-14 | Per-course certificate templates; credentials render HTML, not PDF | Thin | S4 |
| G-15 | Roster export, birthdays, roster announcement | Thin | S3 |
| G-16 | Instructor notes + module-level student assessment | Absent | S4 |
| G-17 | Assignment dashboard, reminders, mark-received, offline; resubmission silently overwrites | Thin + defect | S5 |
| G-21 | No date-of-birth column on `users` (blocks birthdays) | Absent | S3 |
| G-18 | Exam proctor event log, termination, offline grading, results announcement | Thin | S5 |
| G-19 | No realtime transport | Absent | S9 |
| G-20 | Reusable module catalog and lecture materials | Thin | Backlog |

---

## 7. Items this analysis promotes out of the SPIMS parking lot

Six of the gaps above are recorded in [`PARKING-LOT.md`](../../PARKING-LOT.md) as deliberately
deferred. They are
listed here so the SPIMS phase owner accepts each promotion explicitly rather than discovering it in
a diff.

| Parked item | Gap | Recommendation |
|---|---|---|
| Full REST JSON API surface | G-01 | **Promote.** It is the premise of the mobile requirement. |
| Mobile native apps | G-01 | **Promote** the API; client apps stay out of this plan's scope. |
| WebSockets / SSE | G-19 | **Promote, late (S9)**, with a polling fallback so earlier phases do not depend on it. |
| Attendance "excused" state | G-03 | **Promote.** An SIS cannot report attendance credibly without excuses. |
| Hard exam proctoring | G-18 | **Partially promote** — port the proctor *event log* and termination; do not add lockdown-browser-class enforcement. |
| WhatsApp notifications | G-12 | **Keep parked.** Build the channel abstraction; leave the driver unimplemented. |

Two further items stay parked and are explicitly out of scope: parent/guardian roles (Khedma's
guardian check-in path is not being ported) and multi-tenancy.
