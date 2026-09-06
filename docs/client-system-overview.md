# SPIMS — Client System Overview

**What this document is:** a plain-language picture of what is **already in the live SPIMS system** today, so school leadership and partners can see how students, teachers, and staff work in it.

**Product:** SPIMS (Student Information System + Learning Management System) for Spims, a Coptic Orthodox online school.

**Live sites**

| Environment | Address |
|---|---|
| Production | [https://spims-edu.com](https://spims-edu.com) |
| Staging (preview) | [https://staging.spims-edu.com](https://staging.spims-edu.com) |

The product is a **website**. It is designed for phones as well as computers. There is no separate mobile app yet.

---

## 1. In one sentence

A student can create an account in Arabic, apply to a program, enroll, study week by week (video, reading, assignments, quizzes), sit a timed exam, join a live Zoom class, pay an invoice, and later receive a transcript or certificate that anyone can verify with a public link.

Staff can run admissions, curriculum, teaching, grading, live sessions, and money — with a full audit trail of important changes.

---

## 2. Languages, look, and access

| Capability | What is in the system |
|---|---|
| Languages | **Arabic** (primary, right-to-left), **English**, and **French**. Users switch language at any time. |
| Appearance | Light, dark, or follow the device. School branding (site name, logos, colors, favicon) is editable by administrators. |
| Sign-in | Email + password, with email verification by one-time code. Forgot-password uses the same code flow. |
| Accounts | One person can hold more than one role (for example instructor and student). Permissions are combined. |
| Devices | Works in a browser on phone, tablet, and desktop. Navigation includes a sidebar on desktop and a bottom bar on phones. |

---

## 3. Who uses SPIMS (seven roles)

| Role | What they own |
|---|---|
| **Student** | Apply, enroll, learn, submit work, sit exams, pay, see grades, transcript, and wallet |
| **Instructor** | Course content, assessments, grading, announcements, discussions, **lock grades** |
| **Teaching Assistant (TA)** | Content, announcements, grading, discussions — **cannot** lock final grades |
| **Academic Admin** | Programs, courses, offerings, translations, grading schemes, **reopen** locked grades |
| **Administrative Admin** | Users, admissions, forms, semesters, enrollment overrides, branding |
| **Financial Admin** | Prices, invoices, payments, refunds, wallet points, donations, finance reports |
| **Super Admin** | Full access, role-permission matrix, security, audit log, system health |

A person only sees the menus and actions their roles allow.

---

## 4. Student experience

### Discover and apply

- Public **home page** and **course catalog** (filter by program / standalone / free / paid; search by code or title).
- **Interest flags** so students can mark courses they want offered.
- **Offering preview** — Week 1 content is visible before enrollment; later week titles are listed.
- **Application forms** built by the school (text, dates, choices, checkboxes, file uploads). Students save drafts, submit, and track status: draft → submitted → under review → accepted / waitlisted / rejected.

### Enroll and progress

- Register into a **cohort** (semester) or **self-paced** offering, subject to:
  - registration windows
  - prerequisites
  - program membership (except standalone courses)
  - credit / course caps per semester
  - seat capacity (overflow goes to a **waitlist**)
  - **financial holds** (block registration until finance clears them)
- **Drop** during add/drop; **withdraw** (W) after that, within the withdrawal window. Refunds follow school rules.
- **Degree audit** — checklist of required and elective courses, with remaining work and overall progress.
- Warning if a new enrollment would **clash** with another live-session schedule.

### Learn

- **Dashboard** showing my courses, next live session, items due soon, wallet snapshot, and recent notifications.
- **Course player** with weeks that unlock by date (cohort) or by completing the previous week (self-paced).
- Content types: **video** (Vimeo), **reading / PDF**, **text lesson**, **assignment**, **quiz**, **exam**, **discussion**.
- **Announcements** from the teaching team.
- Mark a week complete and see progress.

### Assess and grades

- **Assignments** — submit work (including file upload).
- **Exam runner** — timed attempt, autosave, server-side clock, progress, confirm-before-submit. On a phone, one question at a time by default. Soft integrity: leaving the page is recorded.
- Question types: multiple choice (single/multi), true/false, short answer, essay, matching, fill-in-the-blank, numeric, ordering, file upload.
- Objective items are scored automatically. Essays can receive an **AI suggested score** for the instructor to review (when an AI key is configured).
- **My grades** — only **released** items. Running and final grades when the gradebook allows.
- **Official transcript** of posted academic records.

### Pay and give

- **Invoices** created on enrollment (free courses are marked paid automatically). Due date typically 14 days.
- **Dual currency:** USD and EGP, kept separate — no automatic conversion.
- **Wallet** with four balances: EGP money, USD money, EGP points, USD points.
- **Split pay:** use wallet money, wallet points, and/or a payment gateway on the same invoice.
- Gateways intended for production: **PayPal** (USD), **Paymob** / **Cashier** (EGP). Staff can also record **cash, bank transfer, or cheque** and verify them.
- **Receipt** with a serial number after payment.
- **Donations** with a designation.

### Live class and community

- List of upcoming **Zoom** sessions; join when the window is open.
- **Discussion boards** — threads, posts, mentions; participation can be graded.
- **In-app notifications** (and email when mail is configured): session reminders (~24 hours and ~15 minutes), discussion replies, admissions/finance events.

### Credentials

- View issued **transcripts**, **program certificates**, and **standalone course certificates**.
- Anyone (including employers) can open a **public verification link** and confirm the credential is valid.

---

## 5. Instructor and TA experience

Instructors and TAs work from a **Teach** hub listing the offerings they staff.

For each offering they can:

| Area | What they can do |
|---|---|
| **Content** | Build weeks and items (video, reading, text, assignment, quiz, exam, discussion) |
| **Assessments** | Question banks, attach questions, create quizzes/exams, release results, override scores |
| **Gradebook** | Weighted components (assignment, quiz, exam, attendance, discussion, other); seed from a template; enter scores; **submit**; **lock** (instructor only) |
| **Live** | Schedule Zoom sessions (the system blocks overlapping sessions because one host license is assumed); import or override attendance |
| **Discussions** | Configure the board, moderate threads, grade participation |
| **Announcements** | Post updates that appear in the course player |
| **Roster** | See enrolled students |

**Grade lock** posts official records to the transcript. A TA cannot lock. Only an **Academic Admin** can **reopen** a locked gradebook.

---

## 6. Academic administration

Academic admins (and others with view rights) manage the curriculum:

- **Programs** — diploma, certificate, or degree; credit and semester caps; elective requirements; passing threshold; certificate signatory.
- **Courses** — credit hours, default USD/EGP prices, free flag, standalone flag, **prerequisites**, student interest counts.
- **Offerings** — attach a course to a semester (cohort) or run it self-paced; seat capacity; attendance threshold; staff assignment (instructor / TA); **regional price overrides** by country.
- **Academic years and semesters** — including registration / add-drop / withdrawal windows.
- **Assessment templates** — default gradebook blueprints (weights must total 100%).
- **Grading schemes** — letter bands, percent ranges, GPA points, passing flag.
- **Translations** — human or AI-assisted course/program text; academic staff **verify** before use.
- **Credentials** — issue or regenerate transcripts and certificates when program or course requirements are met.

---

## 7. School administration (admissions, users, branding)

Administrative admins can:

- **Create and suspend users** and assign roles.
- **Build application forms** (field types, required flags, document uploads).
- **Review applications** in a queue (filter by status), record accept / reject / waitlist with notes. Accepted applicants can be **matriculated** into the program.
- **Override enrollment**, manage **waitlists**, and place or lift **financial holds**.
- **Theme editor** — school name, light/dark logos, favicon, and color tokens, with a live preview.
- **Issue credentials** (shared with academic admin).

---

## 8. Finance administration

Financial admins can:

- Set **offering prices** (including regional overrides).
- See the **invoice queue**; create manual invoices.
- **Record and verify** cash / transfer / cheque payments.
- **Approve refunds** (credited back to the student’s wallet).
- **Grant points** or **top up** wallet money.
- View **reports**: outstanding balances and paid revenue, split by USD and EGP.

Students and staff with permission can also **donate**.

Money is stored as **integer minor units** (cents / piastres). USD and EGP never mix or auto-convert.

---

## 9. Super Admin (school operations)

The Super Admin console is for platform operators:

- **Security** — including flushing sessions if needed.
- **Audit log** — who changed what, on which record.
- **Observability** — queue health, failed jobs, last backup time.
- **Scheduled tasks** and **system tests** pages.
- **Roles hub** — adjust which role may do which action (the permission matrix). Super Admin itself bypasses checks.

Every important write (enrollment, payment, grade lock, admissions decision, and similar) is recorded in the audit log.

---

## 10. Typical journeys already supported

1. **Guest → student:** register → verify email → set password → browse catalog → apply → wait for decision.
2. **Accepted student → first course:** enroll (or join waitlist) → pay invoice (wallet / gateway / staff-verified cash) → open course player → watch Week 1 → submit assignment → take quiz.
3. **Exam day:** start attempt → autosave while answering → timer expires or student submits → objective items scored; essays await instructor (with optional AI suggestion).
4. **Live class:** staff schedule Zoom → students get reminders → join in the window → attendance imported or overridden.
5. **End of offering:** instructor locks gradebook → records post to transcript → admin issues certificate → public QR/link verifies it.
6. **Finance:** regional price on invoice → student splits wallet + gateway → receipt issued → refund (if any) returns to wallet.

---

## 11. What is configured vs. what still needs school setup

The **workflows above are built**. A few connections depend on school/IT credentials being present in production:

| Area | Ready in the product | Needs school configuration |
|---|---|---|
| Online card / wallet gateways | PayPal, Paymob, and Cashier are wired; a safe test/mock path exists for demos | Live keys so real charges complete in production |
| Zoom | Scheduling, join, attendance, reminders | Zoom app credentials for real meeting links |
| Email | OTP, decisions, receipts, reminders | Production mail server so messages leave the log and reach inboxes |
| Video | Vimeo embed in the course player | Vimeo IDs / account for hosted videos |
| File uploads | Application documents, assignment files, logos | Object storage (S3-compatible) for production files |
| AI assist | Translation draft + essay score suggestion | Google/Gemini API key; without it, staff work unaided |

Without those secrets, the school can still run admissions, teaching, grading, and **manual** payments end to end.

---

## 12. Not in this version (on purpose)

These are **out of the current product**, so clients should not expect them yet:

- WhatsApp notifications
- Native iOS / Android apps (the site is mobile-friendly)
- Parent / guardian accounts
- Multi-school / multi-campus tenancy (SPIMS is one school)
- Hard exam proctoring (camera lockdown); only soft integrity (focus-loss logging) is present
- Multiple concurrent Zoom hosts (the scheduler assumes **one** licensed host)
- Public marketing website beyond the in-app landing and catalog

---

## 13. How to review it with the school

1. Open [https://spims-edu.com](https://spims-edu.com) (or staging) and switch language to Arabic, English, or French.
2. Walk the **student** path: catalog → application → enrollment → course player → exam → wallet.
3. Walk the **Teach** path: content → assessment → gradebook lock.
4. Walk **admissions** and **finance** queues as the matching admin roles.

A demonstration dataset (sample programs, courses, offerings, and role accounts) can be loaded in staging for a guided tour. Production should use real staff and student accounts only.

---

*This overview describes the system as implemented for client briefing. It is not a technical specification. Internal design notes live in the project repository for the implementation team.*
