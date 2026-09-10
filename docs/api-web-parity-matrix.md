# API ↔ Web Parity Matrix

> **Parity rule**: No new student or instructor API endpoint merges without shipping or explicitly
> deferring its web equivalent in this file. `deferred: out-of-scope` is not a valid reason — state
> the actual constraint (phase, dependency, or cost/benefit decision).

Last updated: 2026-09-10  
Wave: W6 (Step 15)

---

## How to read this table

| Column | Meaning |
|---|---|
| **Service** | The domain/feature area |
| **Student API** | `api.v1.*` route name (mobile/SPA endpoint) |
| **Student Web** | Named web route the student can reach in a browser |
| **Instructor Web** | Named `teach.*` route for the instructor |
| **Admin Web** | Named `admin.*` route for staff/admin |

A cell that is not yet implemented carries: `deferred: <actual reason>`.

---

## Events

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| List events | `api.v1.events.index` | `events.index` | deferred: instructors manage events through admin panel | `admin.events.index` |
| My events | `api.v1.events.mine` | `events.mine` | deferred: no instructor-specific event view (admin handles) | `admin.events.index` |
| Event detail | `api.v1.events.show` | `events.show` | deferred: no separate teach view; admin view covers | `admin.events.show` |
| Reserve seat | `api.v1.events.reserve` | deferred: reservation is done via event detail page (events.show) | deferred: not applicable — instructors don't self-reserve | deferred: admin manages registrations via admin.events.show |
| Cancel reservation | `api.v1.events.cancel` | deferred: cancellation UI integrated in events.show | deferred: not applicable | deferred: admin-side cancellation on roadmap (S6) |
| Check-in verify | `api.v1.events.check-in.verify` | `attendance.check-in` | deferred: check-in is kiosk/mobile-first; web fallback is attendance.check-in | deferred: admin check-in management via admin.events.show |
| Session check-in | `api.v1.sessions.check-in` | `attendance.check-in` | `teach.attendance.code` | deferred: admin observes via admin.live.index |

---

## Surveys / Feedback

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| List surveys | `api.v1.feedback.surveys.index` | `student.surveys.index` | `teach.surveys.index` | `admin.surveys.index` |
| Survey detail | `api.v1.feedback.surveys.show` | `student.surveys.show` | `teach.surveys.show` | `admin.surveys.show` |
| Submit survey | `api.v1.feedback.surveys.submit` | deferred: submission done on student.surveys.show form | deferred: not applicable — instructors don't submit student surveys | deferred: not applicable |
| Survey report | deferred: no API export for survey results yet (S7 analytics phase) | deferred: no student-facing results view (by design — anonymous) | `teach.surveys.report` | `admin.surveys.report` |
| Create survey | deferred: authoring is instructor/admin only | deferred: not applicable | `teach.surveys.store` (POST) | deferred: admin manages via admin.surveys.index |
| Publish survey | deferred: no student publish action | deferred: not applicable | `teach.surveys.publish` (POST) | deferred: admin uses teach panel |
| Close survey | deferred: no student close action | deferred: not applicable | `teach.surveys.close` (POST) | deferred: admin uses teach panel |
| Reveal submission | deferred: no student reveal action | deferred: not applicable | `teach.surveys.reveals.store` (POST) | deferred: admin uses teach panel |

---

## Projects

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| List offering projects | `api.v1.offerings.project-assessments` | `student.projects.index` | `teach.projects.index` | deferred: admin views via admin.offerings.show |
| My projects | `api.v1.projects.show` | `student.projects.mine` | deferred: instructor sees all teams via teach.projects.index | deferred: admin views via offering panel |
| Project detail | `api.v1.projects.show` | `student.projects.show` | `teach.projects.show` | deferred: admin views via offering panel |
| Upload submission file | deferred: upload via api.uploads.store then submission endpoint | `student.projects.files.destroy` (delete only; upload via HTMX) | deferred: instructor doesn't upload student submissions | deferred: not applicable |
| Delete submission file | `api.v1.projects.submission-files.destroy` | `student.projects.files.destroy` | deferred: not applicable | deferred: not applicable |
| Submit deliverable | `api.v1.projects.deliverables.submit` | deferred: form on student.projects.show | deferred: not applicable | deferred: not applicable |
| Peer evaluation | `api.v1.projects.peer-evaluations.store` | deferred: form on student.projects.show | deferred: instructor views results via teach.projects.show | deferred: admin views via offering panel |
| Pending peer evals | `api.v1.projects.peer-evaluations.pending` | deferred: badge count on student.projects.mine | deferred: not applicable | deferred: not applicable |
| Instructor grade project | `api.v1.teach.projects.grade` (POST) | deferred: not applicable | `teach.projects.student-score` / `teach.projects.team-score` (POST) | deferred: admin uses teach panel |
| Instructor review submission | `api.v1.teach.project-submissions.review` (POST) | deferred: not applicable | `teach.projects.submissions.review` (POST) | deferred: admin uses teach panel |
| View submission | `api.v1.teach.project-assessments.teams` | deferred: not applicable | `teach.projects.submissions.show` | deferred: admin uses teach panel |
| Announce project | deferred: no student announcement trigger | deferred: not applicable | `teach.projects.announce` (POST) | deferred: admin uses teach panel |
| Merge/move teams | deferred: not student-facing | deferred: not applicable | `teach.projects.merge` / `teach.projects.move` (POST) | deferred: admin uses teach panel |
| Publish assessment | deferred: not student-facing | deferred: not applicable | `teach.projects.publish` (POST) | deferred: admin uses teach panel |
| Join/leave project | `api.v1.project-assessments.join` / `.leave` (POST) | deferred: action buttons on student.projects.index | deferred: not applicable | deferred: not applicable |

---

## Live Quiz

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| Join session | `api.v1.live-quiz.join` (POST) | `live-quiz.join` | deferred: instructor controls via teach panel | deferred: admin observes via admin.live.index |
| Session state | `api.v1.live-quiz.sessions.show` | `live-quiz.sessions.show` + `live-quiz.sessions.state` | `teach.live-quiz.session` | deferred: admin observes only |
| Submit answer | `api.v1.live-quiz.sessions.answer` (POST) | deferred: answered via live-quiz.sessions.show JS | deferred: not applicable | deferred: not applicable |
| Host start | `api.v1.teach.live-quiz.host.start` (POST) | deferred: not applicable | `teach.live-quiz.start` (POST) | deferred: admin uses teach panel |
| Launch session | `api.v1.teach.live-quiz.sessions.launch` (POST) | deferred: not applicable | `teach.live-quiz.launch` (POST) | deferred: admin uses teach panel |
| Close session | `api.v1.teach.live-quiz.sessions.close` (POST) | deferred: not applicable | `teach.live-quiz.close` (POST) | deferred: admin uses teach panel |
| End session | `api.v1.teach.live-quiz.sessions.end` (POST) | deferred: not applicable | `teach.live-quiz.end` (POST) | deferred: admin uses teach panel |
| Show results | `api.v1.teach.live-quiz.sessions.results` (POST) | deferred: not applicable | `teach.live-quiz.results` (POST) | deferred: admin uses teach panel |
| Create quiz | deferred: not student-facing | deferred: not applicable | `teach.live-quiz.store` (POST) | deferred: admin uses teach panel |
| List offering quizzes | deferred: no student quiz list endpoint | `live.index` | `teach.live-quiz.index` | `admin.live.index` |
| Add questions | deferred: not student-facing | deferred: not applicable | `teach.live-quiz.questions.store` (POST) | deferred: admin uses teach panel |

---

## Assignments

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| List assignments | `api.v1.offerings.assignments` | `assignments.show` (single) | `teach.assignments.index` | deferred: admin views via admin.offerings.show |
| Assignment detail | `api.v1.assignments.show` | `assignments.show` | `teach.assignments.submissions.index` | deferred: admin views via offering panel |
| Submit assignment | `api.v1.assignments.submit` (POST) | deferred: form on assignments.show | deferred: not applicable | deferred: not applicable |
| Resubmit assignment | `api.v1.assignments.resubmit` (POST) | deferred: button on assignments.show | deferred: not applicable | deferred: not applicable |
| List submissions | `api.v1.teach.assignments.submissions` | deferred: not applicable | `teach.assignments.submissions.index` | deferred: admin views via offering panel |
| Submission detail | deferred: no individual submission read endpoint | deferred: not applicable | `teach.assignments.submissions.show` | deferred: admin uses teach panel |
| Grade submission | `api.v1.teach.submissions.grade` (POST) | deferred: not applicable | `teach.assignments.submissions.grade` (POST) | deferred: admin uses teach panel |
| Bulk grade | deferred: no bulk-grade API endpoint | deferred: not applicable | `teach.assignments.bulk-grade` (POST) | deferred: admin uses teach panel |
| Mark received | `api.v1.teach.submissions.mark-received` (POST) | deferred: not applicable | `teach.assignments.mark-received` (POST) | deferred: admin uses teach panel |
| Remind unsubmitted | `api.v1.teach.assignments.remind-unsubmitted` (POST) | deferred: not applicable | `teach.assignments.remind` (POST) | deferred: admin uses teach panel |
| AI grade suggest | deferred: no API endpoint (web-only UI feature) | deferred: not applicable | `teach.assignments.submissions.ai-suggest` (POST) | deferred: admin uses teach panel |
| Instructor list assignments | `api.v1.teach.offerings.assignments` | deferred: not applicable | `teach.assignments.index` | deferred: admin views via offering panel |

---

## Assessments

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| List assessments | `api.v1.offerings.assessments` | deferred: assessments listed on learn.offering / learn.week | deferred: manage via admin.assessments.create | `admin.assessment-templates.index` |
| Assessment detail | `api.v1.assessments.show` | `assessments.show` | deferred: instructor sees via teach.assessments.attempts | `admin.assessments.show` |
| Start assessment | `api.v1.assessments.start` (POST) | deferred: start button on assessments.show | deferred: not applicable | deferred: not applicable |
| Attempt detail | `api.v1.attempts.show` | `assessments.runner` | deferred: not applicable | `admin.attempts.proctor` |
| Attempt timer | `api.v1.attempts.timer` | `assessments.timer` | deferred: not applicable | deferred: admin observes via proctor |
| Save attempt | `api.v1.attempts.save` (POST) | deferred: auto-save via assessments.runner JS | deferred: not applicable | deferred: not applicable |
| Submit attempt | `api.v1.attempts.submit` (POST) | deferred: submit via assessments.runner | deferred: not applicable | deferred: not applicable |
| Focus loss | `api.v1.attempts.focus-loss` (POST) | deferred: triggered from assessments.runner JS | deferred: not applicable | deferred: admin sees via proctor |
| Grade answer | `api.v1.teach.answers.grade` (POST) | deferred: not applicable | `teach.assessments.grade` (POST) | deferred: admin uses teach panel |
| Announce results | `api.v1.teach.assessments.announce-results` (POST) | deferred: not applicable | `teach.assessments.announce` (POST) | deferred: admin uses teach panel |
| List attempts (instructor) | `api.v1.teach.assessments.attempts` | deferred: not applicable | `teach.assessments.attempts` | deferred: admin uses teach panel |
| Create assessment | deferred: not student-facing | deferred: not applicable | deferred: creation managed by admin | `admin.assessments.create` |
| Proctor view | deferred: not applicable | deferred: not applicable | deferred: admin-only action | `admin.attempts.proctor` |

---

## Attendance

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| My attendance | `api.v1.attendance.mine` | `attendance.index` | deferred: instructor views roster not personal attendance | deferred: admin sees via admin.attendance.report |
| Offering attendance | `api.v1.offerings.attendance.mine` | deferred: offered on learn.offering completion widget | deferred: instructor sees via teach.attendance.index | deferred: admin sees via admin.reports.attendance |
| Attendance report | `api.v1.teach.offerings.attendance.report` | deferred: not applicable | `teach.attendance.index` + CSV via `teach.attendance.report.csv` | `admin.attendance.report` |
| Session roster | `api.v1.teach.sessions.roster` | deferred: not applicable | `teach.attendance.show` | deferred: admin sees via admin.live.index |
| Mark attendance | `api.v1.teach.sessions.attendance` (POST) | deferred: not applicable | `teach.attendance.mark` (POST) | deferred: admin uses teach panel |
| Fill missing | `api.v1.teach.sessions.fill-missing` (POST) | deferred: not applicable | `teach.attendance.fill-missing` (POST) | deferred: admin uses teach panel |
| Excuse absence | deferred: no excuse API endpoint | deferred: not applicable | `teach.attendance.excuse` (POST) | deferred: admin uses teach panel |
| Close session | `api.v1.teach.sessions.close` (POST) | deferred: not applicable | `teach.attendance.close` (POST) | deferred: admin uses teach panel |
| Reopen session | deferred: no reopen API endpoint | deferred: not applicable | `teach.attendance.reopen` (POST) | deferred: admin uses teach panel |
| Check-in code | `api.v1.teach.sessions.check-in-code` (POST) | deferred: not applicable | `teach.attendance.code` (POST) | deferred: admin uses teach panel |
| Import from JSON | `api.v1.teach.live-sessions.attendance.import` (POST) | deferred: not applicable | `teach.live.attendance.import` (POST) | deferred: admin uses teach panel |
| Attendance policy | deferred: no policy read API | deferred: not applicable | deferred: policy configured by admin | `admin.attendance.policy` |

---

## Finance

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| List invoices | `api.v1.invoices.index` | `finance.index` | deferred: instructors have no financial access (by design) | `admin.finance.index` |
| Invoice detail | `api.v1.invoices.show` | `finance.invoices.show` | deferred: not applicable | `admin.finance.invoices.show` |
| Invoice checkout | `api.v1.invoices.checkout` (POST) | deferred: checkout triggered from finance.invoices.show | deferred: not applicable | deferred: admin-initiated payments are out-of-phase |
| Payment receipt | `api.v1.payments.receipt` | `finance.receipts.show` | deferred: not applicable | deferred: admin sees via finance.invoices.show |
| Wallet balance | `api.v1.wallet` | deferred: wallet shown on finance.index | deferred: not applicable | deferred: admin views via admin.finance.index |
| Finance reports | deferred: no API for finance reports | deferred: not applicable | deferred: not applicable | `admin.finance.reports` + `admin.reports.finance` |
| Donations | `api.v1.donations.store` (POST) | `donate.create` | deferred: not applicable | deferred: admin views via admin.finance.index |

---

## Enrollment

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| List enrollments | `api.v1.enrollments.index` | `enrollments.index` | deferred: instructor sees roster via teach.index | `admin.enrollments.index` |
| Enroll | `api.v1.enrollments.store` (POST) | deferred: enrollment triggered from catalog.index or courses.public.show | deferred: not applicable | deferred: admin enroll via admin.enrollments.index |
| Drop | `api.v1.enrollments.drop` (POST) | deferred: drop button on enrollments.index | deferred: not applicable | deferred: admin uses admin.enrollments.index |
| Withdraw | `api.v1.enrollments.withdraw` (POST) | deferred: withdraw button on enrollments.index | deferred: not applicable | deferred: admin uses admin.enrollments.index |
| Waitlist | deferred: no API waitlist endpoint | deferred: waitlist prompt on courses.public.show | deferred: not applicable | `admin.enrollments.waitlist` |
| Degree audit | `api.v1.degree-audit.show` | `enrollments.audit` | deferred: not applicable | deferred: admin views via admin.users.show |
| Degree audit what-if | `api.v1.degree-audit.what-if` (POST) | deferred: interactive on enrollments.audit | deferred: not applicable | deferred: admin uses student profile |
| Course interest | `api.v1.catalog.courses.interest` (POST) | deferred: interest button on catalog.index / courses.public.show | deferred: not applicable | deferred: admin tracks via admin.reports.admissions |
| Application forms | `api.v1.application-forms.show` | `applications.create` | deferred: not applicable | `admin.application-forms.index` / `.show` |
| Apply | `api.v1.applications.store` (POST) | `applications.index` | deferred: not applicable | `admin.applications.index` / `.show` |
| Submit application | `api.v1.applications.submit` (POST) | deferred: submit button on applications.create | deferred: not applicable | deferred: admin reviews via admin.applications.show |

---

## Programs

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| Program catalog | deferred: catalog.index covers programs + courses | `programs.catalog.index` | deferred: instructors browse via catalog | `admin.programs.index` |
| Program detail | deferred: no program-detail API endpoint | `programs.catalog.show` | deferred: instructors browse via catalog | `admin.programs.show` |
| Create program | deferred: not student-facing | deferred: not applicable | deferred: admin-only action | `admin.programs.create` |
| Edit program | deferred: not student-facing | deferred: not applicable | deferred: admin-only action | `admin.programs.edit` |
| Attach course | deferred: not student-facing | deferred: not applicable | deferred: admin-only action | `admin.programs.show` (attach/detach) |
| Detach course | deferred: not student-facing | deferred: not applicable | deferred: admin-only action | `admin.programs.detach-course` |

---

## Courses

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| Catalog | `api.v1.catalog.index` | `catalog.index` | deferred: instructors browse via catalog | `admin.courses.index` |
| Course detail (public) | `api.v1.catalog.courses.show` | `courses.public.show` | deferred: instructors browse via catalog | `admin.courses.show` |
| Course player | deferred: content consumed via api.v1.items.show + api.v1.offerings.weeks | `courses.player` (redirects to learn.offering) | deferred: teach panel covers instructor content view | deferred: admin previews via admin.offerings.show |
| Learn offering | `api.v1.offerings.weeks` + `api.v1.offerings.weeks.items` | `learn.offering` + `learn.week` + `learn.item` | deferred: instructors use teach panel content preview | deferred: admin uses offerings.preview |
| Content item | `api.v1.items.show` | `learn.item` | `teach.show` (teaches toward content editing) | deferred: admin views via offerings panel |
| Complete item | `api.v1.items.complete` (POST) | deferred: completion triggered from learn.item | deferred: not applicable | deferred: admin tracks via admin.reports.grades |
| Offering completion | `api.v1.offerings.completion` + `.evaluate` | `offerings.completion` | `teach.completion.show` | deferred: admin uses admin.offerings.gradebook.show |
| Grades | `api.v1.offerings.grades` | `grades.index` | `teach.show` (gradebook tab) | `admin.gradebook.show` |
| Transcript | `api.v1.transcript` | deferred: transcript available from grades.index page | deferred: not applicable | deferred: admin views via admin.users.show |
| Discussions | `api.v1.offerings.discussions` + threads | `discussions.board` + `discussions.thread` | `teach.discussions.index` | deferred: admin moderates via offering panel |
| Announcements | `api.v1.announcements.index` / `.show` | `announcements.index` / `.show` | `teach.announcements.store` / `.update` | `admin.communications.report` |
| Create offering | deferred: not student-facing | deferred: not applicable | deferred: admin-only action | `admin.offerings.create` |
| Create course | deferred: not student-facing | deferred: not applicable | deferred: admin-only action | `admin.courses.create` |

---

## Credentials

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| List credentials | `api.v1.me.credentials` | deferred: credentials listed on grades.index / hubs.academic | deferred: not applicable | `admin.credentials.index` |
| Download credential | `api.v1.credentials.download` | `credentials.download` | deferred: not applicable | deferred: admin views via admin.credentials.index |
| Certificate templates | deferred: no student template read endpoint | deferred: not applicable | deferred: admin-only action | `admin.certificate-templates.index` + `.preview` |

---

## Communications

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| Announcements index | `api.v1.announcements.index` | `announcements.index` | deferred: instructors post via teach panel | `admin.communications.report` |
| Announcement detail | `api.v1.announcements.show` | `announcements.show` | deferred: instructor manages own announcements via teach | `admin.communications.report` |
| Dismiss banner | `api.v1.announcements.dismiss-banner` (POST) | deferred: dismiss button on announcements.show | deferred: not applicable | deferred: not applicable |
| Notifications | `api.v1.notifications.index` / `.show` | `notifications.index` | deferred: same notification system (no instructor-only view) | deferred: admin views via admin.communications.report |
| Mark notification read | `api.v1.notifications.read` / `.mark-all-read` (POST) | deferred: action on notifications.index | deferred: same as student | deferred: not applicable |
| Notification settings | `api.v1.notification-settings.show` / `.update` | `settings.notifications.edit` | deferred: same UI as student | deferred: admin manages system templates |
| Email templates | deferred: no API endpoint for email templates | deferred: not applicable | deferred: admin-only action | `admin.email-templates.index` |
| Communications export | deferred: no API export | deferred: not applicable | deferred: not applicable | `admin.communications.export` |
| Publish announcement | `api.v1.teach.announcements.publish` (POST) | deferred: not applicable | `teach.announcements.publish` (POST) | deferred: admin uses teach panel |

---

## Users / Admin

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| My profile | `api.v1.me` | `settings.edit` | deferred: same settings UI | deferred: admin edits via admin.users.show |
| Update picture | `api.v1.me.picture` (POST) | `settings.edit` (picture upload) | deferred: same UI | deferred: admin edits via admin.users.show |
| Preferences | `api.v1.me.preferences` (PUT) | `settings.edit` | deferred: same UI | deferred: not applicable |
| User list | deferred: no student user-list endpoint | deferred: not applicable | deferred: not applicable | `admin.users.index` |
| User detail | deferred: not student-facing | deferred: not applicable | deferred: instructors access student detail via teach.students.show | `admin.users.show` |
| Role assignment | deferred: not student-facing | deferred: not applicable | deferred: admin-only action | `admin.users.roles.destroy` + create |
| Dashboard | `api.v1.dashboard` | `dashboard` | deferred: instructors land on teach.index | deferred: admin uses hubs.admin |
| Roles hub | deferred: no API hub endpoint | `roles.hub` | deferred: same hub | deferred: admin uses hubs.admin |
| Branding | `api.v1.branding` | deferred: branding applied globally; no separate view | deferred: not applicable | `admin.theme.edit` |
| Staff roster (instructor) | `api.v1.teach.offerings.roster` | deferred: not applicable | `teach.attendance.show` (session roster) | deferred: admin views via admin.offerings.show |
| Student notes | `api.v1.teach.offerings.students.notes` | deferred: not applicable | `teach.students.show` | deferred: admin views via admin.users.show |
| Student profile (teach) | `api.v1.teach.offerings.students.show` | deferred: not applicable | `teach.students.show` | `admin.users.show` |
| Gradebook (instructor) | `api.v1.teach.offerings.gradebook` | deferred: not applicable | `teach.show` (gradebook tab) | `admin.gradebook.show` |
| Gradebook lock/unlock | `api.v1.teach.offerings.gradebook.lock` / `.reopen` / `.submit` (POST) | deferred: not applicable | `admin.gradebook.show` (admin-initiated locking) | `admin.gradebook.show` |
| Advising | deferred: no student API for advising | `advising.index` / `advising.show` | deferred: advising is admin/advisor role, not instructor | deferred: admin has full advising access |

---

## Live Sessions

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| List live sessions | `api.v1.teach.offerings.live-sessions` | `live.index` | `teach.live.index` | `admin.live.index` |
| Create session | `api.v1.teach.offerings.sessions.store` (POST) | deferred: not applicable | `teach.attendance.store` (POST) | deferred: admin uses teach panel |
| Session detail | `api.v1.teach.sessions.roster` | deferred: students see sessions via attendance.index | `teach.attendance.show` | deferred: admin sees via admin.live.index |
| Birthdays | `api.v1.teach.offerings.birthdays` | deferred: not applicable | `teach.show` (roster widget) | deferred: admin views via admin.offerings.show |

---

## Completion / Standing

| Service | Student API | Student Web | Instructor Web | Admin Web |
|---|---|---|---|---|
| Offering completion | `api.v1.offerings.completion` | `offerings.completion` | `teach.completion.show` | deferred: admin sees via gradebook |
| Evaluate completion | `api.v1.offerings.completion.evaluate` (POST) | deferred: triggered from offerings.completion | deferred: not applicable | deferred: admin triggers via admin.offering-closing.show |
| Week complete | `api.v1.offerings.weeks.complete` (POST) | deferred: triggered from learn.week | deferred: not applicable | deferred: not applicable |
| Assess student week | `api.v1.teach.offerings.weeks.students.assessment` (PUT) | deferred: not applicable | `teach.completion.assess` (POST) | deferred: admin uses teach panel |
| Standing thresholds | deferred: no API endpoint | deferred: not applicable | deferred: not applicable | `admin.reports.standing.thresholds` |
| Standing report | deferred: no student standing API | `grades.index` (standing indicator) | deferred: instructors see via teach gradebook | `admin.reports.standing` |
| Offering close | `api.v1.teach.offerings.close` (POST) | deferred: not applicable | `teach.offerings.close` (POST) | `admin.offering-closing.show` |

---

*This matrix is authoritative for the parity rule. Update it whenever a new student or instructor
API endpoint is added or deferred, in the same PR that adds or defers the endpoint.*
