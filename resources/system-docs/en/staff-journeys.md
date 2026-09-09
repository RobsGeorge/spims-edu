## Staff journeys overview

Staff work is organized by hub. Role gates control which hubs appear; within a hub, permission keys control individual tiles and actions.

---

## Teach hub (Instructor and TA)

Instructors and TAs open **Teach** from the sidebar (or mobile bottom nav when they can teach). The hub lists offerings they are staffed on.

For each offering they can:

| Area | What they do |
|---|---|
| **Content** | Build weeks and items (video, reading, text, assignment, quiz, exam, discussion) |
| **Assessments** | Question banks, attach questions, create quizzes/exams, release results, override scores |
| **Gradebook** | Weighted components; seed from a template; enter scores; **submit**; **lock** (instructor only) |
| **Live** | Schedule Zoom sessions (system blocks overlapping sessions — one host license assumed); import or override attendance |
| **Discussions** | Configure the board, moderate threads, grade participation |
| **Announcements** | Post updates that appear in the course player |
| **Roster** | See enrolled students; export / announce where permitted |
| **Projects / feedback / live quiz** | Where enabled for the offering (S6E surfaces) |

**Grade lock** posts official records to the transcript. A TA cannot lock. Only an **Academic Admin** can **reopen** a locked gradebook.

### Typical teach flow

1. Open Teach → select offering.
2. Build or adjust week content and gating.
3. Configure assessments and gradebook weights (must total 100%).
4. Schedule live sessions; run class; import attendance.
5. Grade submissions; optionally use AI essay suggestions.
6. Instructor submits then **locks** the gradebook.
7. If a correction is needed after lock → Academic Admin reopens → instructor re-locks.

---

## Academic Admin — curriculum desk

Academic admins use the **Academic** hub (`programs.manage` gate).

| Tile / area | Purpose |
|---|---|
| Programs | Diploma / certificate / degree; credit & semester caps; electives; passing threshold; certificate signatory |
| Courses | Credit hours, default USD/EGP prices, free/standalone flags, prerequisites, interest counts |
| Offerings | Attach course to semester (cohort) or self-paced; seats; attendance threshold; staff (instructor/TA); regional prices |
| Semesters / years | Academic calendar and registration windows (often shared with admin ops) |
| Assessment templates | Default gradebook blueprints |
| Grading schemes | Letter bands, percent ranges, GPA points, passing flag |
| Translations | Human or AI-assisted course/program text; verify before use |
| Credentials | Issue or regenerate transcripts and certificates |
| Advising / reports / surveys | Advising desk, academic reports, staff surveys |

---

## School Admin — admissions, users, theme

Administrative admins use the **Admin** hub (`users.manage` gate).

| Area | What they do |
|---|---|
| **Users** | Create and suspend accounts; assign roles |
| **Application forms** | Field types, required flags, document uploads |
| **Applications queue** | Filter by status; accept / reject / waitlist with notes; **matriculate** accepted applicants |
| **Enrollment admin** | Override enrollment, manage waitlists, place or lift **financial holds** |
| **Theme editor** | School name, light/dark logos, favicon, color tokens, live preview |
| **Help CMS** | Categories and articles for in-app Help (not these System Docs) |
| **Events / communications** | School events and communication reports where enabled |

### Typical admissions flow

1. Build or update the application form for the intake.
2. Students submit → queue shows under review.
3. Decide accept / waitlist / reject with notes.
4. Matriculate accepted applicants into the program.
5. Student enrolls in offerings subject to windows, seats, and holds.

---

## Finance administration

Financial admins (`offerings.pricing` gate for the finance desk) work from **Finance** hub admin tiles; all users still see personal wallet tools.

| Task | Detail |
|---|---|
| Pricing | Offering prices and regional (country) overrides |
| Invoice queue | Enrollment invoices; create manual invoices |
| Manual payments | Record and verify cash / transfer / cheque |
| Refunds | Approve; credit returns to the student wallet |
| Wallet | Grant points or top up money (EGP/USD buckets stay separate) |
| Reports | Outstanding balances and paid revenue, split by USD and EGP |
| Donations | Students/staff with permission can donate; finance oversees |

Money is always **integer minor units** (cents / piastres). No float math; no automatic FX conversion.

---

## Super Admin — control plane

Super Admins open **Super Admin** (shield) for platform operations:

| Section | Contents |
|---|---|
| **People** | Shortcut to users |
| **Access** | Roles hub (permission matrix overrides), security (e.g. flush sessions) |
| **Appearance** | Theme editor |
| **School** | Shortcuts into academics, finance, credentials, admissions, enrollments, reports |
| **Evidence** | Audit log, observability (queues, failed jobs, backups), health |
| **Ops** | Scheduled tasks, system tests, feedback identity reveals |

Every important write (enrollment, payment, grade lock, admissions decision, and similar) is recorded via `AuditLogWriter`. Super Admin bypasses permission checks; empty matrix keys (e.g. `features.manage`, `roles.manage_matrix`) remain Super Admin only unless the Roles hub grants them.

---

## Cross-role collaboration examples

| Scenario | Who acts |
|---|---|
| Student blocked by financial hold | Financial or Administrative Admin lifts hold → student enrolls/pays |
| Locked grade needs correction | Academic Admin reopens → Instructor fixes → Instructor locks again |
| New term setup | Academic Admin: offerings & staff; Administrative Admin: forms & windows; Financial Admin: prices |
| Certificate after completion | Academic or Administrative Admin issues → student shares public verify link |

See [Roles guide](roles-guide.md) and [Portal navigation](portal-navigation.md).
