## How to read this page

Each section is a **step flow** for a core product path. Controllers stay thin; services enforce rules and wrap mutations with `AuditLogWriter::withAudit()` where required.

---

## Auth + OTP

1. Guest registers with email (and profile fields as configured).
2. System creates `OtpToken` and sends mail (or logs OTP in dev).
3. User enters OTP → email verified → sets / confirms password.
4. Login establishes web session; API clients use Sanctum tokens.
5. Forgot-password issues a new OTP → verify → reset password.
6. Locale and theme preference stored on the user for subsequent visits.

---

## Admissions

1. Administrative Admin builds `ApplicationForm` + fields (types, required, uploads).
2. Student opens form → saves **draft** `Application` + field values.
3. Student **submits** → status under review.
4. Admin reviews queue (filter by status) → accept / waitlist / reject with notes.
5. On accept, admin may **matriculate** into `StudentProgram`.
6. Notifications (in-app / email) fire on decisions when mail is configured.
7. Audit log records the decision mutation.

---

## Enrollment + holds + waitlist

1. Student selects cohort or self-paced `CourseOffering` within registration window.
2. Service checks: prerequisites, program membership, credit/course caps, financial **holds**, seat capacity, live-schedule clash warnings.
3. If seats remain → create `Enrollment` → generate `Invoice` (auto-paid if free).
4. If full → place on **waitlist** (`enrollment.waitlist`); promote when a seat opens / admin overrides.
5. Admin may **override** enrollment or place/lift `AdvisingHold` / financial holds.
6. Drop during add-drop; withdraw (W) in withdrawal window → refund rules applied via finance services.
7. Degree audit reads program requirements vs completions / academic records.

---

## Content gating (course player)

1. Offering has ordered `Week` rows with `ContentItem` children.
2. Cohort: week unlocks by scheduled date; self-paced: unlocks when prior week marked complete.
3. Student opens `learn.offering` → only unlocked weeks/items are actionable; later titles may show locked.
4. Completing items/weeks writes `EnrollmentItemCompletion` / `EnrollmentWeekCompletion` and updates progress %.
5. Announcements and discussions attach at offering / board level and appear in the player context.

---

## Assessments / attempts

1. Staff create bank `Question`s and attach to `Assessment` (quiz/exam) with open/close windows.
2. Student starts `AssessmentAttempt` → server stores start time; timer is server-authoritative.
3. Autosave writes `AttemptAnswer` rows; soft proctor events log focus loss.
4. Submit (or timer expiry) finalizes attempt; objective items scored automatically.
5. Essays may receive AI suggested scores (if Gemini configured) for instructor review.
6. Instructor/TA grades remaining items; results release via announce-results permissions.
7. Student sees scores only when released / gradebook rules allow.

---

## Gradebook lock / reopen

1. Instructor configures `GradebookComponent` weights (total 100%) — often seeded from `AssessmentTemplate`.
2. Scores enter from assessments, assignments, attendance, discussion, etc.
3. Instructor **submits** then **locks** (`gradebook.lock`) → posts `AcademicRecord` lines to transcript.
4. TA cannot lock.
5. Corrections after lock: Academic Admin **reopens** (`gradebook.reopen`) → edits → instructor locks again.
6. Both lock and reopen are audited.

---

## Finance checkout

1. Enrollment (or manual admin action) creates `Invoice` + lines in minor units + currency.
2. Student opens checkout → optional **split**: wallet money, wallet points, gateway remainder.
3. Gateways: PayPal (USD), Paymob/Cashier (EGP); or staff records cash/transfer/cheque and verifies.
4. Success writes `Payment`, updates invoice status, issues receipt serial, posts `WalletTransaction` if wallet used.
5. Refunds credit the wallet; dual currency never auto-converts.
6. Donations create `Donation` records with designation.

---

## Live Zoom

1. Instructor/TA schedules `LiveSession` — scheduler rejects overlaps (one host license assumption).
2. Reminders (~24h / ~15m) enqueue notifications / mail.
3. Students see session on dashboard “next live” and live hub; **join** only inside the join window.
4. After session, attendance import or manual `AttendanceEntry` / overrides.
5. Attendance may feed gradebook components when configured.

---

## Credentials issue + public verify

1. Completion / program rules satisfied (`CompletionResult` / degree audit).
2. Academic or Administrative Admin **issues** `Credential` (transcript, program cert, standalone cert) — may regenerate.
3. Student views credential in portal; public token/QR URL verifies without login.
4. Public page confirms validity, type, and identity fields intended for employers.

---

## Help CMS

1. Administrative Admin manages categories/articles in `admin.help.*` (`help.manage`).
2. Locales stored in `HelpArticleLocale`; audiences in `HelpArticleAudience`.
3. Signed-in users with `help.view` open `help.index` and see articles for their roles/locale.
4. System Docs under `resources/system-docs/` are **not** Help CMS — separate briefing/technical tree.

---

## Related reading

- Client journeys: [student-journey.md](student-journey.md), [staff-journeys.md](staff-journeys.md)
- Authz detail: [roles-permissions.md](roles-permissions.md)
- Models: [database.md](database.md)
