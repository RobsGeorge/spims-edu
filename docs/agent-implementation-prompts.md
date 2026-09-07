# Agent implementation prompts — SPIMS gaps

> **2026-09-07 (`main` @ `acb20cb`):** S0–S8, S6 Wave E, and the S9 domain (events + live quiz on polling) are already on `main`. Prompts **#10 (S5), #11 (S4), #14 (S6 A–D), #18 (S7), #19 (S8), and #20 (S9) are cancelled — do not re-implement.** Keep the bodies below as historical text. Leftover work is listed in [system-code-review.md](system-code-review.md) (discussion attachments via `ObjectStorageService`, advisor what-if on the student API, program-level standing overrides, Paymob `integration_id`, `permissions:sync` for newer keys, optional Reverb, WhatsApp driver, parked items). `gradebook.reopen` and applicant `WITHDRAWN` already landed (`e50faa8` and after).

Copy one prompt per agent run. Each prompt is self-contained: context, files, hard rules, acceptance, and tests. Do not start a later prompt until its **Depends on** line is satisfied. Do not start a cancelled prompt.

**Source:** [system-code-review.md](system-code-review.md) (2026-09-06) plus [academic-roadmap/implementation-plan.md](academic-roadmap/implementation-plan.md) and [demo-accounts.md](demo-accounts.md).

**How to use**

1. Give the agent the **Hard rules (every prompt)** block once, then the numbered prompt body.
2. One prompt = one PR series. Do not batch P0 with S4.
3. After each merge, run `php artisan test --compact` and `vendor/bin/pint --test` on changed files.
4. Skip anything listed under **Out of scope** — those belong to a later prompt or [PARKING-LOT.md](../PARKING-LOT.md).

---

## Hard rules (every prompt)

You are working on SPIMS, a standalone Laravel 10 SIS/LMS at `/workspace`. PHP 8.2. No npm build. Blade + Alpine.

1. Authorization via `AuthorizeService` + `config/permissions.php`. No role-name string comparisons in controllers. A new offering-owned permission key **must** be registered in `config/permission_scopes.php`. A new offering-owned model **must** be registered in `ResourceScopeResolver::offeringIdsFor()`. Scoped keys fail closed: pass the resource.
2. Every mutation through a service wrapped by `AuditLogWriter::withAudit()`.
3. Money = integer minor units + `Currency` enum. No floats. API money is `{ "minor_units", "currency", "formatted" }`.
4. All user-facing strings localized (`lang/ar`, `lang/en`, `lang/fr`). Arabic is primary RTL.
5. Additive migrations only. ULID PKs, `foreignUlid()`.
6. Tests ship in the same PR. Add new suites to both `phpunit.xml` and `.github/workflows/ci.yml`.
7. Mailers and third-party keys optional; missing key degrades, never blocks.
8. Staff an instructor/TA on the offering in fixtures: `$this->staffOffering($user, $offering)`.
9. Do not implement WhatsApp, native apps, multi-tenant, parent/guardian, Title IV, library, bookstore, housing, SCORM/LTI, or lockdown browser unless the prompt names it.

Read `CLAUDE.md`, `docs/system-code-review.md` §5–§6, and the files named in the prompt before editing.

---

## Priority index

| # | Priority | Prompt | Why this order |
|---|---|---|---|
| 1 | P0 | Fix teach workspace 500 | Live instructor page is broken; blocks every teach demo |
| 2 | P0 | Assessment start + player fallback holes | Unreleased exams startable; wrong quiz can bind |
| 3 | P0 | Production-safe defaults | Mock payments / webhook secrets / OTP logs can leak to prod |
| 4 | P1 | Demo seeder classroom + money | Client walkthrough is empty after `migrate:fresh --seed` |
| 5 | P1 | Catalog and offering operator CRUD | Services exist; registrars cannot edit |
| 6 | P1 | Enrollment admin UI + term-scoped caps | Engine exists; no forms; caps count every term |
| 7 | P1 | Unify course players + uploads | Two progress models; assignment file submit is fake |
| 8 | P1 | Exam runner completeness + results visibility | Grader supports types the UI does not render |
| 9 | P1 | Gradebook grid + CSV | Instructors have a summary table, not a gradebook |
| 10 | P2 | **S5** assessment completion | **Already on main / cancelled** — do not re-implement |
| 11 | P2 | **S4** completion + PDF credentials | **Already on main / cancelled** — do not re-implement |
| 12 | P2 | Admissions / user / discussion polish | Create-only forms, thin profiles, visibility bug |
| 13 | P2 | Live recurrence + finance refund UI | Dead service methods; bursar cannot request refunds |
| 14 | P3 | **S6** student API waves A–D | **Already on main / cancelled** — do not re-implement |
| 15 | P3 | Reporting pack + academic standing | Populi-class ops on current schema |
| 16 | P3 | Advising-lite + what-if audit | Highest-value Populi feature that fits this school |
| 17 | P3 | Payment plans + live gateways | Real tuition; needs gateway accounts |
| 18 | P4 | **S7** team projects | **Already on main / cancelled** — do not re-implement |
| 19 | P4 | **S8** instructor API | **Already on main / cancelled** — do not re-implement |
| 20 | P4 | **S9** realtime, live quiz, events | **Already on main / cancelled** — do not re-implement |
| 21 | P4 | SSO/MFA, WhatsApp driver, donor CRM | Integrations; only after a provider is chosen |

---

## 1. P0 — Fix teach workspace 500

**Depends on:** nothing.

```
Implement: fix GET /teach/{offering} 500 and add a regression test.

Confirmed on a running seed (2026-09-06): ins1@spims.test hitting
GET /teach/{TH101 offering} returns 500.

Root cause: app/Http/Controllers/Teach/TeachController.php show() does
  $offering->load(['course', 'semester', 'weeks.contentItems', 'staff.user']);
Week (app/Models/Week.php) defines items(), not contentItems().
RelationNotFoundException is in storage/logs/laravel.log.

Do this:
1. Change the eager-load to weeks.items (or add a contentItems() alias on Week
   that delegates to items() — prefer renaming the load to match the model).
2. Grep the repo for weeks.contentItems and contentItems on Week; fix every
   leftover.
3. Confirm resources/views/teach/show.blade.php iterates the same relation name.
4. Add tests/Feature/Portal/TeachOfferingShowTest.php (or extend
   tests/Feature/Portal/PortalD4TeachTest.php):
   - staff an instructor on an offering that has at least one Week
   - GET /teach/{offering} is 200
   - a second instructor NOT on that offering is 403
   - a student is 403
5. Run: php artisan test --testsuite=Portal --compact
        php artisan test --compact
        vendor/bin/pint --test on changed files

Acceptance: instructor can open /teach/{offering} content, roster, and
announcements tabs without 500. Attendance sub-routes stay 200.

Out of scope: teach-side content authoring, SpeedGrader, S5/S4.
```

---

## 2. P0 — Assessment start + player fallback holes

**Depends on:** nothing (can run in parallel with #1).

```
Implement: close two confirmed LMS integrity holes.

Hole A — unreleased exams are startable.
  app/Services/Assessment/AttemptService.php start() checks enrollment and
  isOpen() but does NOT check assessment.released.
  Any student who knows the ULID can start an unreleased exam.

Hole B — CoursePlayerService::mapItem() assessment fallback.
  app/Services/Learning/CoursePlayerService.php mapItem() (around the
  Assignment/Assessment lookup): if content_item_id is missing it falls back
  to title match, then to the FIRST released assessment on the offering.
  That can deep-link the wrong quiz/exam.

Do this:
1. AttemptService::start() must refuse unless released === true (and still
   refuse outside the window). Use a localized validation/authorization error,
   not a 500. Audit the refusal only if you already audit start(); do not
   invent a new audit event unless one exists.
2. Remove the "first released assessment on the offering" fallback. If the
   content item is not linked, render the item as unlinked (no exam CTA),
   never a different assessment. Title-match is acceptable only if it is
   scoped to the same offering AND unique; otherwise no link.
3. Tests (extend tests/Feature/Assessment/AssessmentEngineTest.php and
   tests/Feature/Offerings/LearningPlayerTest.php):
   - unreleased assessment: enrolled student POST start → 403 or 422; no
     AssessmentAttempt row
   - released + open window: start still works (existing tests stay green)
   - content item of type QUIZ/EXAM with no linked assessment: player does
     not expose another assessment's start URL
4. Run Assessment + Offerings suites, then full php artisan test --compact.

Acceptance: unreleased exams cannot start; a week item never launches the
wrong assessment.

Out of scope: proctor escalation (prompt #10), runner UI for extra question
types (prompt #8).
```

---

## 3. P0 — Production-safe defaults

**Depends on:** nothing (parallel with #1 and #2).

```
Implement: fail-safe production defaults for payments, webhooks, OTP, tokens.

Confirmed risks in the 2026-09-06 review:
- config/services.php PAYMENTS_MOCK_AUTO_COMPLETE defaults true
  (PaymentService auto-completes gateway charges if env is unset)
- Default webhook secrets: paypal-test, paymob-test, cashier-test, zoom-test
- OtpService logs the plaintext OTP when MAIL_MAILER=log
- config/sanctum.php expiration is null (tokens never expire)
- AuthorizationException on expected 403s is reported as local.ERROR
  (noisy, not a hole)

Do this:
1. Mock auto-complete MUST be true only when APP_ENV is local or testing.
   In staging/production, missing PAYMENTS_MOCK_AUTO_COMPLETE means false.
   Add a Hardening test: with APP_ENV=production and the env unset,
   checkout does not mark a payment Completed without a verified webhook.
2. Refuse to verify webhooks if the configured secret is still a *-test
   default AND APP_ENV is production. Log a clear error; return 503 or 401.
   Do not change local/test behaviour (tests use those defaults).
3. OTP: never log the code at info/error. If mailer is log, write a one-line
   notice that an OTP was issued (purpose + user id), not the digits.
   Keep the existing Mail::log path so local verify still works via the
   mail log / UI, not via laravel.log.
4. Set Sanctum expiration to a finite value (e.g. 30 days) in config.
   Document it in .env.example. Add an Api test that a token older than
   expiration is 401 — only if you can do this without a brittle sleep;
   otherwise unit-test the config value and document the setting.
5. In app/Exceptions/Handler.php, do not report App\Exceptions\AuthorizationException
   at error level (dontReport or reportable returning false). 403 pages stay 403.
6. Tests: extend tests/Feature/Hardening/HardeningReleaseTest.php and
   tests/Feature/Finance/FinanceFlowTest.php. Full suite must stay green.

Acceptance: a production .env that forgets mock/secret flags cannot silently
take fake payments or accept test HMAC. OTP digits are not in laravel.log.

Out of scope: real PayPal/Paymob SDKs (prompt #17), MFA/SSO (prompt #21).
```

---

## 4. P1 — Demo seeder: classroom + money

**Depends on:** #1 (so /teach/{offering} can be opened after seed). Prefer #2 merged so seeded quizzes cannot be started unreleased.

```
Implement: make migrate:fresh --seed client-demo-ready.

Today DemoDataSeeder (database/seeders/DemoDataSeeder.php) creates roles,
programs, offerings, mixed applications, and 12 enrollments. After seed,
these counts are ZERO: ContentItem, Assessment, Assignment, Invoice, Payment,
WalletAccount, Announcement, LiveSession, ClassSession, AttendanceEntry,
Credential, AcademicRecord, DiscussionPost, GradebookComponent,
ApplicationFieldValue.

Read docs/demo-accounts.md §7 before writing.

Extend DemoDataSeeder AFTER the existing enrollment block. Call the same
services the UI uses (not raw Eloquent inserts for mutations that have
services). Staff actors already exist (ins1, ins2, ta1, student1/6/7/9).

Must seed:
1. TH101 and BI101 Week 1: 2–3 ContentItem rows (TEXT and READING). No
   Vimeo id unless you use a clearly fake placeholder and the player
   degrades. English copy is fine; do not invent Coptic theology claims
   — use short generic lesson titles already implied by the course name.
2. One QuestionBank + 4 released, in-window questions (MCQ single, T/F,
   short, numeric) on TH101 via QuestionBankService + AssessmentService.
   Attach to a content item if the schema allows.
3. GradebookService::addComponent or seedFromTemplate on TH101 (Exam +
   Attendance).
4. InvoiceService::createForEnrollment for each non-free enrollment.
   PaymentService::recordManual + verifyManual on student9's first invoice
   so /admin/finance is not empty. WalletService::credit a small EGP
   money balance on student1.
5. AttendanceService: open one class session on TH101, mark student1
   PRESENT, student6 LATE, student7 ABSENT (excuse student7).
6. AnnouncementService::draft + publish from ins1 to TH101.
7. LiveSessionService::schedule one session in the next 24h on TH101
   (mock Zoom is fine).
8. DiscussionService: one thread + one student post on TH101.
9. ApplicationFieldValue answers on at least student1 and student3
   ("Why join?" / "Parish name").
10. Staff dual@spims.test as Instructor on FREE1 and enroll them in the
    ET101 self-paced offering so the dual-role account is usable.

Do NOT seed a locked gradebook + credential unless scores exist and
CredentialService can issue honestly. A hollow certificate is worse than none.

Also:
- Add tests/Feature/Database/DemoDataSeederTest.php (suite Database):
  with SEED_DEMO_DATA=true, after seed: >=1 ContentItem, >=1 Invoice,
  >=1 ClassSession, >=1 Announcement, dual user has OfferingStaff on
  FREE1 and an Enrollment. Keep phpunit.xml default SEED_DEMO_DATA=false
  for other tests; enable it only in this test.
- Update docs/demo-accounts.md §4 counts and remove "zero rows" claims
  that you fixed.
- migrate:fresh --seed must still complete on SQLite and the CI Postgres job.

Acceptance: after migrate:fresh --seed, student1 can open a TH101 lesson,
see an announcement, see an invoice, and see attendance history;
fin@spims.test sees at least one invoice; ins1 can open /teach/{TH101}
(requires prompt #1).

Out of scope: S4 certificates, real Zoom, real payments.
```

---

## 5. P1 — Catalog and offering operator CRUD

**Depends on:** nothing. Parallel with #4.

```
Implement: wire existing update services so registrars can maintain catalog
and offerings after create.

Today:
- ProgramService::update and CourseService::update exist
  (app/Services/Academics/ProgramService.php, CourseService.php) but have
  NO controller actions or routes.
- Offerings cannot change semester, capacity, or status after create except
  setPricing. No remove-staff action.
- Semesters/years are create-only (SemesterService).
- Application forms are create-only (ApplicationFormController).

Do this:
1. Admin routes + controllers:
   - PUT/PATCH /admin/programs/{program} → ProgramService::update
   - PUT/PATCH /admin/courses/{course} → CourseService::update
   - optional POST detach for program_courses and course_prerequisites
   - PUT /admin/offerings/{offering} for seat_capacity, dates, status
     (DRAFT/OPEN/IN_PROGRESS/COMPLETED — use OfferingStatus). Status
     transitions must be audited. Do not allow students to register on
     DRAFT (fix EnrollmentService / EnrollmentController index which
     currently includes DRAFT).
   - POST /admin/offerings/{offering}/staff/{staff} delete or unstaff
   - PUT /admin/academic-years/{year} and /admin/semesters/{semester}
   - PUT /admin/application-forms/{form} (name, active) and ability to
     add/deactivate fields
2. Blade: edit forms on the existing show/create pages. Reuse validation
   from store. Localized labels only.
3. Enrollment register must refuse offerings whose status is not Open
   (and cohort offerings outside the semester registration window — already
   implemented).
4. Tests: extend ProgramBuilderTest, CoursePrerequisiteAndInterestTest,
   SemesterAndOfferingTest, AdmissionsFlowTest.
   - Academic admin can update program name / deactivate
   - Student cannot
   - Student cannot register on a DRAFT offering
   - Unstaff removes access (instructor 403 on scoped action)

Acceptance: a registrar can correct a typo, archive a course, open a
draft offering, and remove a TA without a developer.

Out of scope: catalog versioning, bulk CSV import, room scheduling.
```

---

## 6. P1 — Enrollment admin UI + term-scoped caps

**Depends on:** nothing. Strongly pair with #5 (DRAFT register fix).

```
Implement: operator UI for enrollment exceptions, and fix semester-scoped
credit/course caps.

Today (app/Services/Enrollment/EnrollmentService.php):
- assertCanRegister counts ALL ENROLLED rows for the student program, not
  the current term — cross-semester enrollments incorrectly block.
- Admin routes exist with no Blade:
    POST /admin/enrollments/override
    POST /admin/users/{user}/financial-hold
    GET  /admin/offerings/{offering}/waitlist
- Waitlist page has no nav link from admin/offerings/show. Academic admin
  received 403 on waitlist in the 2026-09-06 crawl — confirm the
  permission key (likely enrollment override / waitlist view) and either
  grant ACADEMIC_ADMIN read or document that only ADMINISTRATIVE_ADMIN
  may manage waitlist. Prefer Academic Admin R + Administrative Admin F.
- EnrollmentStatus::Completed is never written by app code.
- is_audit / GradeType::Audit has no registration path.
- Drop/withdraw routes have no permission middleware (ownership is only
  in the service). Add middleware that still allows the owner.

Do this:
1. Fix cap math: count ENROLLED (and WAITLISTED if they hold a seat)
   whose offering.semester_id is the target semester. Self-paced
   offerings without a semester count against "current" only if
   overlapping dates. Add a regression in EnrollmentEngineTest.
2. Admin UI:
   - From /admin/users/{user} or a new enrollments admin page: toggle
     financial hold, override-register into an offering (existing service).
   - Link "Waitlist" from /admin/offerings/{offering} show.
   - Waitlist: show FIFO order; optional promote (if service already
     promotes on drop, document it; do not invent reorder unless cheap).
3. On GradebookService::lockGrades / postAcademicRecord: set that
   enrollment status to Completed when a passing band is posted.
4. Drop/withdraw: middleware permission:enrollment.register (own) or
   equivalent existing key; service ownership check stays.
5. Tests: EnrollmentEngineTest + a new Portal/admin test that the hold
   form 403s for a student and 200/302 for Administrative Admin.

Acceptance: caps are per term; adm@ can place a hold and open a waitlist
from the offering page; locking a passing grade marks the enrollment
completed.

Out of scope: graduation application (prompt #11), advising holds
beyond financial (prompt #16).
```

---

## 7. P1 — Unify course players + assignment uploads

**Depends on:** #1. Do not ship #4's content items onto a still-split progress model if you can avoid it; if #4 merged first, this prompt must migrate existing progress_percent.

```
Implement: one student player, one progress model, real file submit.

Today:
- /learn/* (LearnController + LearningProgressService) progress =
  completed items / total items
- /courses/* (CoursePlayerController + CoursePlayerService) progress =
  completed weeks / total weeks
  Both write enrollments.progress_percent.
- Enrollments index CTA points at /courses/{offering}
  (LearningPlayerTest around the CTA assertion).
- /courses/* has no permission middleware; access is service-only.
- CoursePlayerService::mapItem() N+1 queries Assignment/Assessment
  per item (also related to prompt #2).
- assignments/show.blade.php accepts a pasted file_url.
  POST /api/uploads exists (ObjectStorageService) and is unused.
  Prefix submissions is defined but unused.

Do this:
1. Pick LearningProgressService (item-level) as the source of truth.
   CoursePlayerService::completeWeek may still mark a week complete by
   completing remaining items, but it must call LearningProgressService
   and not write a week-ratio percent.
2. Make /courses/{offering} a redirect to /learn/{offering} (301/302)
   OR render the same LearnController payload. Do not leave two UIs.
3. Put the same permission middleware on any remaining /courses route
   that /learn uses (permission:offerings.view + OfferingAccessService).
4. Assignment submit: multipart file via ObjectStorageService under
   submissions/{user}/{ulid}.ext. Validate size/MIME against
   assignment.allowed_file_types. Store the returned path, not a
   client-supplied URL. Reject paths the user does not own.
5. Eager-load assignments/assessments for a week in one query.
6. Tests:
   - progress_percent after completing 1 of 4 items is 25, whether the
     student hit /learn or the old /courses complete-week route
   - assignment submit with a fake file_url is 422
   - assignment submit with uploaded file creates a submission whose
     path starts with submissions/ and is readable via temporaryUrl
   - unauthenticated /courses/{id} redirects to login

Acceptance: one player, one percent, real uploads. Existing
LearningPlayerTest and AssignmentResubmissionIntegrityTest stay green.

Out of scope: video watch-percent, SCORM, media library UI.
```

---

## 8. P1 — Exam runner completeness + results visibility

**Depends on:** #2 (released check). Complementary to #10 (S5) but shipable first.

```
Implement: runner UI for every QuestionType the grader already scores,
and honour results_visibility / reveal_answers.

Today:
- QuestionType: MCQ single/multi, T/F, short, essay, matching, fill blank,
  numeric, ordering, file upload.
- resources/views/assessments/runner.blade.php only renders MCQ single,
  T/F, essay/short/fill, numeric.
- ObjectiveGrader already scores matching (proportional partial credit)
  and ordering; MCQ multi is all-or-nothing (scoreMulti).
- assessments/show.blade.php always lists attempt scores.
  GradebookService has a dead comment that results_visibility has no effect.
- enforce_full_screen / one_at_a_time / no_backtrack / log_focus_loss
  are stored. Focus-loss increments a counter only (S5 will escalate).
  Mobile already forces one-at-a-time.

Do this:
1. Runner UI for MCQ_MULTI (checkbox group), MATCHING, ORDERING,
   FILE_UPLOAD (use the same upload service as prompt #7 if merged;
   otherwise store via ObjectStorageService). Keep Alpine, no npm.
2. Optional: MCQ multi partial credit consistent with matching
   (proportional). Document the rule in the test name.
3. Enforce results_visibility and reveal_answers on
   assessments/show and any grade payload. Hidden until the instructor
   announces (if S5 announce is not in yet, treat released=false OR
   visibility=hidden as "no scores").
4. one_at_a_time / no_backtrack: honour the flags on desktop too when
   set; do not change the mobile default.
5. enforce_full_screen: best-effort Fullscreen API + log a proctor-ish
   event if the existing focus-loss endpoint is the right sink. Do not
   build lockdown browser (parked).
6. Tests: AssessmentEngineTest + a new view/feature test that a matching
   question round-trips an answer and scores; a student cannot see
   another student's score; hidden visibility hides scores.

Acceptance: every QuestionType can be answered in the runner; hidden
results stay hidden.

Out of scope: ProctorService termination (prompt #10), rubrics, plagiarism.
```

---

## 9. P1 — Gradebook grid + CSV

**Depends on:** nothing. Pair with #6 if you also mark enrollments Completed on lock.

```
Implement: a usable instructor gradebook on top of GradebookService.

Today:
- GradebookService computes weighted percent, submit, lock, reopen,
  posts academic_records.
- admin/gradebook/show.blade.php is enrollment-level % + letter only.
  N+1: computeEnrollment() per student per request.
- Add-component <select> omits ATTENDANCE and DISCUSSION kinds
  (ComponentKind exists).
- Weights need not sum to 100; compute renormalizes.
- No CSV export.

Do this:
1. Grid: rows = roster enrollments, columns = gradebook components
   (plus final %). Read-only cells from componentPercent(); link to
   the existing assignment/attempt grade routes. No new scoring math.
2. Include ATTENDANCE and DISCUSSION in the add-component form.
3. Validate or warn when weights ≠ 100; do not silently change the
   renormalize behaviour without a test (keep it, show the sum).
4. GET /admin/offerings/{offering}/gradebook.csv (permission
   gradebook.configure or a new gradebook.export = same grants).
   CSV: student name, email, per-component %, final %, letter.
   Audit the export.
5. Eager-load data so show() is not N+1 (preload attempts, submissions,
   attendance percents for the offering).
6. Tests: lock still posts records (AssessmentEngineTest);
   AttendanceGradebookParityTest stays green; new test that CSV
   contains the enrolled student and 403s for a student token.

Acceptance: an instructor can see every student × component and
download CSV.

Out of scope: drop-lowest, curve, extra credit, what-if, SpeedGrader
queue (mention in PARKING or a follow-up). Extra credit / drop-lowest
are Band A in the review — only add if cheap after the grid.
```

---

## 10. P2 — S5 assessment completion

> **DO NOT START — already on main.** Prompt cancelled 2026-09-07 (`main` @ `acb20cb`).

**Depends on:** #2. #8 recommended first so offline/file flows have a UI.

```
Implement academic roadmap S5 — assessment completion.
Read docs/academic-roadmap/implementation-plan.md section "S5" in full
and docs/academic-roadmap/execution-order.md. Close G-17 / G-18.

Already done (do not redo): assignment silent-overwrite fix (commit
1b02276) — versions table + attempt_no. Remaining work is the
resubmission_deadline window and staff UI.

Schema (additive):
- assignments: delivery_mode (ONLINE/OFFLINE), resubmission_deadline,
  late_penalty_percent if not already present. allow_resubmission may
  already exist — check the 2026_09_05 versioning migration.
- assignment_submissions: received_at, received_by_id, resubmission_of_id
  if missing.
- New proctor_events: attempt_id, student_id, event_type, warning_number,
  details, created_at.
- assessment_attempts: proctor_warnings, terminated_for_cheating,
  terminated_at, terminated_by_id.
- New assessment_result_announcements.

Services:
- AssignmentService: markReceived, remindUnsubmitted (S2 ChannelDispatcher,
  once per window, skip OFFLINE), staff dashboard counts, enforce
  resubmission_deadline.
- ProctorService: record events, escalate, terminate at threshold.
  Wire POST /attempts/{attempt}/focus-loss into it. Terminated attempts
  cannot resume or submit. Do not delete the attempt. Audit terminate.
- AssessmentService::announceResults — flip released, dispatch once,
  write announcement row.
- Bulk offline grade entry for OFFLINE assessments.

Permissions (register in permissions.php AND permission_scopes.php for
offering-owned keys; resolver for new models):
  assignments.dashboard, assignments.remind, assignments.mark_received
  (INSTRUCTOR/TA = O)
  assessments.proctor (INSTRUCTOR = O)
  assessments.announce_results (INSTRUCTOR = O, ACADEMIC_ADMIN = F)
  assessments.clear_termination (ACADEMIC_ADMIN = F)

Web: teach/admin dashboard for submission counts; remind button;
mark-received; announce-results; admin clear-termination.

Tests (suite Assessment — add to phpunit.xml + ci.yml if new files only;
suite already exists):
- AssignmentDashboardTest, AssignmentReminderTest, AssignmentResubmissionTest,
  AssignmentOfflineTest
- ProctorEscalationTest — N focus-loss → warnings → terminate → cannot
  submit; admin clear makes it gradeable
- ResultsAnnouncementTest — announce once; re-announce does not duplicate
- AssessmentEngineTest stays green

Acceptance: a terminated attempt cannot be submitted; offline assignment
can be marked received without a file; announcing releases scores once;
unsubmitted reminders fire once through S2.

Out of scope: lockdown browser, S4, S6 wave C (but keep service methods
API-ready).
```

---

## 11. P2 — S4 completion + PDF credentials

> **DO NOT START — already on main.** Prompt cancelled 2026-09-07 (`main` @ `acb20cb`).

**Depends on:** #10 (announce path) and S3 (already on main). Do not start before S5 is merged if you will reuse announce/dispatch.

```
Implement academic roadmap S4 — course completion, criteria, credentials.
Read docs/academic-roadmap/implementation-plan.md section "S4" in full.

Schema (additive, ULIDs):
- completion_criteria (course or offering): kind MIN_GRADE / MIN_ATTENDANCE
  / REQUIRED_ITEM / MIN_DISCUSSION, threshold, is_required
- offering_closings: status OPEN / GRADING_LOCKED / ANNOUNCED / CLOSED,
  grace_marks, timestamps, actors
- completion_results: met_criteria json, outcome COMPLETED /
  NOT_COMPLETED / PENDING
- certificate_templates: course or global, locale, title, body,
  background_path, signature_path
- student_notes: offering_id, student_id, author_id, body,
  visibility STAFF_ONLY
- module_student_assessments: week, student, rating, comment, assessed_by

Conjunction rule (mandatory): if MIN_GRADE and MIN_ATTENDANCE are both
is_required, failing attendance forces NOT_COMPLETED even at 90% grade.

Services:
- CompletionService::evaluate(offering) — idempotent, re-runnable
- OfferingClosingService — lockGrading DELEGATES to
  GradebookService::lockGrades; applyGraceMarks; announce via S2
  ChannelDispatcher; close issues credentials via CredentialService
  (exactly one per completing student, idempotent)
- CertificateTemplateService — course → global → locale fallback
- PDF via DomPDF behind file_url; HTML fallback if renderer missing
  (rule 7). Fix ReceiptPdfService the same way in this PR.
- Also fix CredentialService::programRequirementsMet so program
  certificates require elective_credits_required, not required
  courses only. Set StudentProgramStatus::Completed when the program
  audit + electives are met.

Permissions (scopes + resolver):
  completion.view, completion.configure (ACADEMIC_ADMIN = F)
  offering.close (ACADEMIC_ADMIN = F; INSTRUCTOR = O for lock/announce only)
  certificate_templates.manage, student_notes.*, module_assessment.*

Web: criteria editor, closing wizard (consequence copy in ar/en/fr),
template preview, teach cohort view, staff-only notes (students 403
even on their own notes).

API (thin, same services): GET /api/v1/me/credentials,
GET /api/v1/credentials/{id}/download,
GET /api/v1/offerings/{id}/completion (own vs cohort by role).

Tests — new suite Completion (phpunit.xml + ci.yml):
- CompletionCriteriaTest, ClosingWorkflowTest, CertificateIssuanceTest,
  CertificateTemplateTest (Arabic RTL PDF or HTML-fallback assertion),
  StudentNotesPrivacyTest, Api/CompletionApiTest
- CredentialService elective regression

Acceptance: admin sets "70% and 75% attendance", evaluates, grace-marks
two students, announces, closes; Arabic certificates verify at
/verify/{token}; student sees only their result.

Out of scope: S6 remaining waves, team projects, official transcript
request workflow beyond issue+verify+PDF.
```

---

## 12. P2 — Admissions, users, discussions polish

**Depends on:** #5 for form update routes if you split; otherwise include form CRUD here only if #5 did not.

```
Implement: finish thin SIS/LMS surfaces that already have tables.

Admissions
- ApplicationService submit currently skips SUBMITTED (DRAFT → UNDER_REVIEW).
  Either use SUBMITTED as the post-submit state or stop advertising it.
  saveAnswers must not allow edits after UnderReview.
- Unique (applicant_id, program_id) blocks reapplication. Add
  decided_at + REJECTED/WITHDRAWN exception or a new cycle column so a
  rejected student can apply next term. Additive migration.
- Persist ApplicationFieldValue in the demo only if #4 did not.
- Replace role-name checks in ApplicationService / ApplicationReviewController
  with permission keys (admissions.decide / admissions.review). Keep
  reviewer_id assignment as data, not as a RoleType compare in the controller.
- Paginate /applications and /admin/applications.
- is_reviewer toggle on admin user form.

Users
- Edit name, locale, country, date_of_birth, notify_email, is_reviewer.
- Assign/remove non-admin roles (roles.assign). Super-admin-only for
  admin roles (existing empty keys).
- Do not add addresses/emergency contacts unless you add a small
  JSON/EAV column and a localized form — keep it minimal (name, phone,
  locale, dob, reviewer, roles, suspend).

Discussions
- Enforce ThreadVisibility::PrivateToInstructor on board/thread queries.
- Paginate threads/posts.
- Remove hard-coded English placeholders in
  resources/views/discussions/board.blade.php (use lang keys).
- Align ensureBoard() defaults: LearnController vs DiscussionService
  disagree on allow_student_threads for self-paced.
- Attachments: accept uploaded paths or drop the unused column from
  the UI contract (do not drop the column).

Tests: AdmissionsFlowTest, UserAdminTest, LiveCommsTest (discussions
section) + a visibility test that a student cannot GET a private thread.

Acceptance: reviewer without the permission key is 403; private threads
are invisible to other students; user edit works; rejected applicant
can apply again next cycle.

Out of scope: FERPA field-level privacy, alumni, bulk import.
```

---

## 13. P2 — Live recurrence + finance refund / receipt UI

**Depends on:** #3 before touching payment defaults. #11 if you want PDF receipts in the same pass — otherwise HTML receipts stay until S4's ReceiptPdfService fix.

```
Implement: expose dead LiveSessionService::scheduleRecurrence() and the
student refund request that already exists on PaymentService.

Live
- app/Services/Live/LiveSessionService.php scheduleRecurrence() has no
  HTTP route. Admin UI (LiveSessionAdminController::store) only calls
  schedule().
- Add POST /admin/offerings/{offering}/live/recurrence with weekly
  rule fields already on SessionRecurrence. Permission live.schedule.
- assertNoOverlap() currently loads all LiveSession rows — replace with
  a date-range query.
- Document that production Zoom still needs keys; mock stays for tests.

Finance
- PaymentService::requestRefund has no route. Add student
  POST /finance/payments/{payment}/refund-request (permission finance.pay,
  owner-only) and keep admin approve at existing
  POST /admin/finance/refunds/{refund}/approve.
- Audit requestRefund (it currently writes no audit).
- Paginate student finance index (last-20 wallets is too small for a
  demo once #4 seeds invoices).
- Receipt: if S4 did not land, keep HTML but link it from the invoice
  page; if S4 landed, use the PDF.

Tests: LiveCommsTest recurrence creates N sessions and overlap 422;
FinanceFlowTest student request + admin approve; student cannot request
on someone else's payment.

Acceptance: academic admin can schedule a weekly live block; student
can request a refund; bursar can approve; every path audited.

Out of scope: live PayPal SDK (prompt #17), calendar UI, breakout rooms.
```

---

## 14. P3 — S6 student API waves A–D

> **DO NOT START — already on main.** Prompt cancelled 2026-09-07 (`main` @ `acb20cb`).

**Depends on:** S1+S2 (done), S3 (done), #10 before Wave C, #7 before assignment submit, existing finance before Wave D. Wave E is prompt #18/#20.

```
Implement academic roadmap S6 student mobile API, waves A–D only.
Read docs/academic-roadmap/mobile-api-spec.md and implementation-plan.md S6.

Rules:
- Thin controllers in App\Http\Controllers\Api\V1. Same services as web.
- Same permission keys + resource (S0).
- Money envelope { minor_units, currency, formatted }.
- Accept-Language ar/en/fr already exists (SetApiLocale). Keep it.
- Update docs/api/openapi.yaml; OpenApiCoverageTest must stay green.

Wave A (S1/S2 already have login, logout, me, branding, announcements,
notifications, settings — fill gaps only):
  GET /api/v1/me/preferences, PATCH same, POST /api/v1/me/picture
  (ObjectStorageService), GET /api/v1/dashboard (StudentDashboardService)

Wave B (S3 done):
  offerings, weeks, items, item complete, grades (released only),
  transcript, degree audit, attendance history (may already exist),
  credentials list/download

Wave C (requires S5 = prompt #10):
  assignments list/detail/submit/resubmit
  assessments list/start/save/submit/timer
  discussions read/post

Wave D:
  catalog, application forms, apply, application status,
  enrollments register/drop/withdraw, invoices, checkout, wallet, receipts

Tests (suite Api):
- StudentApiScopeTest parameterized over every id-bearing student route
- StudentApiParityTest vs web service output for grades, attendance,
  completion
- Extend ApiLocaleTest, OpenApiCoverageTest
- Student cannot read another student's invoice/grade/attendance by ULID

Acceptance: a bearer-token student can complete in Arabic: login,
announcement, complete a week item, submit assignment, sit timed exam,
see attendance, see a released grade, pay an invoice (mock).

Out of scope: Wave E (projects, surveys, events, live quiz), instructor
API (prompt #19), native apps.
```

---

## 15. P3 — Reporting pack + academic standing

**Depends on:** #6 (completed enrollments) and #9 (gradebook data). #11 for completion outcomes if you include graduation counts.

```
Implement: registrar/finance CSV reports and GPA-based academic standing
on the current schema. No new product line — query + export.

Reports (permission: reuse audit.view or add reports.view =
ADMINISTRATIVE_ADMIN F, ACADEMIC_ADMIN R, FINANCIAL_ADMIN R for money):
- Headcount by program / offering / semester
- Admissions funnel (status counts)
- Attendance % by offering (AttendanceService already aggregates)
- Finance: outstanding vs paid totals (admin/finance/reports exists —
  extend with aging buckets 0–14 / 15–30 / 31+ using invoice.due_date)
- Grade distribution after lock

Each report: HTML table + CSV. Audit CSV downloads. Paginate HTML.
ar/en/fr headers.

Academic standing:
- Settings or program-level thresholds (e.g. good >= 2.0, probation
  < 2.0, suspension < 1.0) stored as integer hundredths of a GPA point
  or decimal columns already used by grade_bands.gpa_points.
- Job or on-lock hook writes standing onto student_programs
  (additive column). Financial hold is NOT auto-applied (that is
  advising policy — prompt #16).
- Admin list of students on probation.

Tests: a locked cohort produces expected CSV rows; a student 403s;
standing flips when cached_gpa crosses the threshold.

Acceptance: dean can export Fall headcount and attendance; bursar can
export aging; one student is flagged probation in tests.

Out of scope: IPEDS, BI dashboards, at-risk ML, parent letters.
```

---

## 16. P3 — Advising-lite + what-if degree audit

**Depends on:** #6, #11 (real completion + electives). Highest-value Populi feature that fits this school.

```
Implement: advisor assignment, staff notes, registration lock, what-if
audit. Not a full Populi advising CRM.

Schema (additive):
- advisor_assignments: student_id, advisor_id, program_id nullable
- advising_holds: student_id, kind (ADVISING/DISCIPLINE), reason,
  created_by, released_at, released_by
- Reuse student_notes from S4 if #11 landed; otherwise the same
  STAFF_ONLY notes table

Behaviour:
- EnrollmentService::assertCanRegister also fails closed on an active
  advising hold (in addition to financial hold).
- DegreeAuditService::audit already exists — add
  DegreeAuditService::whatIf($studentProgram, array $hypotheticalCourseIds)
  that does not persist. UI: student or advisor ticks extra catalog
  courses and sees remaining required/elective credits change.
- Admin/advisor UI: assign advisor (roles: Academic Admin F,
  Instructor O on their advisees). Advisor sees a roster of advisees
  and the what-if page.
- Permission keys: advising.assign, advising.hold, advising.view.
  Instructors scoped to assigned students only (not offering-scoped —
  document the check next to AuthorizeService; do not pretend it is
  offering-scoped). Fail closed if no student resource is passed.

Tests: hold blocks register; release allows register; what-if does not
write academic_records; student cannot read another student's notes;
instructor cannot hold a student they do not advise.

Acceptance: advisor places a hold, student register 422, hold released,
what-if shows electives dropping if they "add" ET101.

Out of scope: appointment scheduling, degree-plan worksheets beyond
what-if, US SAP official rules (standing in #15 is enough).
```

---

## 17. P3 — Payment plans + live gateways

**Depends on:** #3 (safe defaults). Do not enable live charges until secrets are real.

```
Implement: installment plans on the existing invoice ledger, and real
gateway charge/verify behind GatewayRouter.

Today:
- GatewayRouter::attemptLiveCharge is a placeholder. Mock auto-complete
  path is used in tests (keep it for APP_ENV=testing).
- DonationService marks payments Completed immediately.
- No payment plans.

Schema (additive):
- payment_plans: invoice_id, installment_count, interval (MONTH),
  start_on
- payment_plan_installments: due_on, amount_minor, currency, status,
  payment_id nullable
- Amounts integer minor units. Sum of installments MUST equal
  invoice.total_minor.

Services:
- PaymentPlanService::create, generate installments, mark due, dunning
  reminder via S2 ChannelDispatcher once per overdue installment
- GatewayRouter: implement PayPal (USD) and Paymob or Cashier (EGP)
  using env keys; verifySignature must use the provider's real scheme
  when keys exist, not json_encode HMAC, for live. Keep the test HMAC
  path when APP_ENV=testing.
- Donations: same checkout as invoices; do not complete until webhook
  or mock-in-testing.

Web: student finance — "Pay in 3" on an unpaid invoice; bursar can
attach a plan. Dunning is a scheduled command (register in Kernel +
superadmin scheduled-tasks list — that list currently omits
communications:fire-reminders; add both).

Tests: installments sum to invoice; early payoff closes the plan;
live charge is not called in testing; webhook idempotency stays
(FinanceFlowTest); production-default tests from #3 stay green.

Acceptance: student splits TH101 into 3 EGP installments; overdue
installment sends one mail via S2; Paymob/PayPal code paths exist but
tests still mock.

Out of scope: Title IV, 1098-T, GL export, bookstore, multi-currency
FX.
```

---

## 18. P4 — S7 team projects

> **DO NOT START — already on main.** Prompt cancelled 2026-09-07 (`main` @ `acb20cb`).

**Depends on:** S0 (done). Can start after P0 if you have a free agent; do not collide with S4/S5 tables.

```
Implement academic roadmap S7 — team projects.
Read implementation-plan.md section S7 in full.

Port the MODEL from the Khedma academic hub, not the table names.
Attach to course_offerings.

Tables: project_assessments, projects, project_memberships,
project_membership_events, project_phases, project_deliverables,
project_deliverable_submissions, project_submission_files,
project_change_requests, project_grade_criteria, project_grades,
project_peer_evaluations.

Rules that must be copied exactly:
- Join only inside the window; leave exactly once; seat frees immediately.
- Team grade propagates unless a per-student override exists.
- Peer evaluation is INFORMATIONAL and must NEVER write
  project_member_grades / project_grades. Assert byte-for-byte unchanged
  in PeerEvaluationDoesNotGradeTest.
- Announce is what pushes into a gradebook component of kind project
  (add ComponentKind if missing).
- Deliverable points sum to assessment max in deliverables mode.

Permissions + scopes + resolver for every new offering-owned model.

Tests — new suite Projects (phpunit.xml + ci.yml):
TeamFormationTest, DeliverableSubmissionTest, ProjectGradingTest,
PeerEvaluationTest, PeerEvaluationDoesNotGradeTest,
ProjectChangeRequestTest, ProjectScopeTest, Api/ProjectsApiTest.

Acceptance: team forms, submits, staff grades, announce once, peer
scores visible to staff only as aggregates, never as grades.

Out of scope: S9 live quiz, S8 instructor API (add endpoints only if
cheap; otherwise S8 consumes these services).
```

---

## 19. P4 — S8 instructor API

> **DO NOT START — already on main.** Prompt cancelled 2026-09-07 (`main` @ `acb20cb`).

**Depends on:** #10, #11, #18 (anything you expose). S0+S3 already on main.

```
Implement academic roadmap S8 — instructor mobile API.
Read mobile-api-spec.md instructor section and implementation-plan.md S8.

Surface: teach offerings, attendance (lock_version + 409), gradebook
read, submission grading, attempt override, submit/lock with a
confirmation token, roster/birthdays/notes, announcements, assignment
dashboard / mark received / remind, projects review/grade/announce,
live join/import.

Rules:
- permission.scoped on every route. Token ability role:INSTRUCTOR or
  role:TA.
- Irreversible actions (gradebook.lock, offering.close,
  projects.announce) require a confirmation field echoing a
  server-issued token from a preceding GET. Replay rejected.
- TA lock/reopen/announce is 403 server-side.

Tests: InstructorApiScopeTest parameterized over the route list,
InstructorApiRoleTest, InstructorConfirmationTest,
InstructorAttendanceApiTest, InstructorGradingApiTest.
OpenAPI coverage.

Acceptance: instructor can take attendance, grade three submissions,
publish, lock from a token; TA cannot lock.

Out of scope: native apps, S9.
```

---

## 20. P4 — S9 realtime, live quiz, events

> **DO NOT START — already on main.** Prompt cancelled 2026-09-07 (`main` @ `acb20cb`). S9 domain (events + live quiz on polling) shipped; Reverb remains optional.

**Depends on:** S1+S2 (done). Last on purpose — new infrastructure.

```
Implement academic roadmap S9 — Reverb, live quiz, events.
Read implementation-plan.md S9.

- Add Laravel Reverb. Document in docs/vps-setup.md. Deploy workflow
  note. EVERY feature degrades to polling (2s quiz, page refresh events)
  when broadcasting is off so CI/local need no websocket.
- Live quiz tables + host/play services. Server-authoritative timing.
- Events: publish, capacity, eligibility, reserve, waitlist, signed QR
  check-in.
- Permissions: live_quiz.*, events.* with scopes where offering-owned.

Tests — new suites LiveQuiz + Events:
LiveQuizLifecycleTest, LiveQuizScoringTest, LiveQuizFallbackTest
(broadcasting off → same state via poll), EventReservationTest,
EventCheckInTest, scope tests.

Acceptance: live quiz runs with Reverb and without; late answers score
zero; QR checks in once.

Out of scope: Framer Motion, marketing site, native apps.
```

---

## 21. P4 — Integrations after a vendor is chosen

**Depends on:** a written decision (provider name + account). Do not start on speculation.

```
Implement ONE of the following per PR, only after the school names the
vendor. Each is a separate agent run.

A) WhatsApp driver
   Channel interface already exists
   (app/Services/Communications/Channels/, ChannelDispatcher).
   Implement OutboundChannel for WhatsApp. Keep unimplemented skip-log
   when the key is missing. PARKING-LOT.md stays honest: driver was
   parked; this prompt un-parks it. Tests: dispatcher hits the driver
   with a fake HTTP client; missing key → communication_logs status
   skipped/unimplemented.

B) SSO (SAML or OIDC) + TOTP MFA
   Do not invent a protocol. Use a maintained Laravel package if it
   stays PHP 8.2 / no npm. Super-admin can still password-login.
   MFA required for SUPER_ADMIN and all *ADMIN roles. Tests for
   unauthenticated SSO callback and recovery codes.

C) Donor CRM lite
   Donations exist. Add campaign, appeal, designation, acknowledgement
   email via S2 templates. No Salesforce. Money still integer minor units.

D) Outbound webhooks + API keys
   School-facing signed webhooks for enrollment.created,
   payment.completed, credential.issued. Rotate-able keys. Never log
   the raw secret.

Out of scope until explicitly reopened: Title IV, library, bookstore,
housing, SCORM/LTI, AI tutor, multi-tenant, parent role.
```

---

## Parallelism (what may run at the same time)

`#10`, `#11`, `#14`, `#18`, `#19`, and `#20` are **cancelled** (already on `main` @ `acb20cb`). Do not schedule them.

```
P0:  #1  ||  #2  ||  #3

P1:  #5  ||  #6  ||  #9
     #4 after #1
     #7 after #1
     #8 after #2

P2:  #10 after #2
     #11 after #10
     #12 || #13 after P0

P3:  #14 after #10 (wave C) — waves A/B/D can start earlier
     #15 after #6/#9
     #16 after #11
     #17 after #3

P4:  #18 anytime after P0 (watch table names vs S4)
     #19 after #10 + #18
     #20 last
     #21 only with a vendor decision
```

## Do not write prompts for

Fully grown as of `main` @ `acb20cb` — do not re-implement: S0 scope, S1 API envelope, S2 comms spine (except WhatsApp driver), S3 attendance, S4 completion + credentials, S5 assessment completion, S6 student API (A–E), S7 team projects, S8 instructor API, S9 domain (events + live quiz on polling; Reverb still optional), enrollment engine rules (except leftover admin polish), money ledger math, public credential verify HTML path, OTP login flow, theme editor, Roles Hub. Prompts #10, #11, #14, #18, #19, #20 are cancelled.

If an agent finds one of those broken, fix forward with a test; do not start a greenfield rewrite.
