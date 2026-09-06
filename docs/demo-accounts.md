# Demo accounts and client dummy data (SPIMS)

This is the playbook for showing SPIMS to school leadership. It covers who to log in as, what is already seeded, what is **empty** after a fresh seed, how to add dummy classroom/money data by hand, and how to put that data into the seeder so the next `migrate:fresh --seed` is client-ready.

Engineering review of the product itself: [system-code-review.md](system-code-review.md).

---

## 1. One-time setup

On a laptop or on staging:

```bash
cp .env.example .env
php artisan key:generate
# Confirm these two lines (they are the defaults):
# SEED_SAMPLE_DATA=true
# SEED_DEMO_DATA=true
php artisan migrate:fresh --seed
php artisan serve
```

Open `http://localhost:8000` (or https://staging.spims-edu.com).

| Flag | Default | Effect |
|---|---|---|
| `SEED_SAMPLE_DATA` | `true` | Tiny extra program `DEMO-DIP` / course `DEMO101` |
| `SEED_DEMO_DATA` | `true` | All accounts and curriculum below |
| `SUPERADMIN_EMAIL` | `robeir.george@outlook.com` | Super-admin identity |
| `SUPERADMIN_PASSWORD` | `Spims@Dev2026!` | Super-admin password **only** |

`php artisan db:seed --class=DemoDataSeeder` alone is **not** enough on an empty database — it skips languages, grading scheme, theme, permissions, and the super admin. Prefer `migrate:fresh --seed`.

Tests force `SEED_DEMO_DATA=false`. Demo users never appear in PHPUnit.

---

## 2. Passwords

| Who | Password |
|---|---|
| Every `@spims.test` demo user | `Spims@Test2026!` |
| Super admin | `Spims@Dev2026!` (or whatever is in `.env`) |

Do not give clients the super-admin password on production. Staging is the right place for a walkthrough.

---

## 3. Accounts — who to hand to whom

Use these in this order. Each row is a **persona**, not just a login.

### Staff (give these to the school team)

| Email | Name | Role | Locale | What they can show today | What is empty until you add data |
|---|---|---|---|---|---|
| `robeir.george@outlook.com` | George Robei | Super Admin | en | Every menu, Roles Hub, audit, health, theme | Same empties as below |
| `adm@spims.test` | Admin Office | Administrative Admin | en | Users, admissions queue (10 applications, mixed status), semesters, branding | Cannot open grading schemes / translations / attendance report (403 — correct) |
| `aca@spims.test` | Academic Dean | Academic Admin | en | Programs, courses, offerings, gradebook, live admin, translations | Gradebook has no components; no exams; waitlist URL 403 for this role |
| `fin@spims.test` | Finance Bursar | Financial Admin | en | Finance hub and reports **pages** | **Zero invoices, payments, wallets** |
| `ins1@spims.test` | Mina Instructor | Instructor | en | Teach list: TH101, BI102, CH101, FREE1. Attendance page loads | **`/teach/{offering}` currently 500s** (relation bug). No lessons, no roster activity |
| `ins2@spims.test` | Mariana Teacher | Instructor | **ar** | Teach list: BI101, LI101, ET101 cohort, TH201. Use this to demo Arabic UI | Same empty classroom |
| `ta1@spims.test` | Yousef Assistant | TA | en | Staffed on TH101, BI101, BI102. Cannot lock grades | Same as instructor |
| `dual@spims.test` | Dual Role | Instructor + Student | en | Proves multi-role accounts | **No enrollment and no offering staff** — switcher with nothing behind it |

### Students (give these to the school to “be a student”)

| Email | Name | Locale | Application | Enrollments | Best use |
|---|---|---|---|---|---|
| `student1@spims.test` | John Student | en | DIP-THEO **Accepted** | TH101 + BI101 enrolled (5%), BI102 **waitlisted** | Primary student walkthrough |
| `student2@spims.test` | Sara Habib | **ar** | DIP-THEO Submitted | none | Arabic applicant; admissions still in flight |
| `student3@spims.test` | Mark Shenouda | en | DIP-THEO Under review | none | Reviewer queue |
| `student4@spims.test` | Mary Guirguis | **fr** | DIP-THEO Waitlisted | none | French UI + waitlisted application |
| `student5@spims.test` | David Bishoy | en | DIP-THEO **Rejected** | none | Rejection state |
| `student6@spims.test` | Hannah Rizk | **ar** | CERT-LIT Accepted | TH101, BI101, BI102 at 30% | Arabic enrolled student |
| `student7@spims.test` | Peter Atallah | en | CERT-LIT Accepted | same three at 35% | Second enrolled student (for attendance marking) |
| `student8@spims.test` | Rebecca Fawzy | en | CERT-LIT **Draft** | none | Unfinished application |
| `student9@spims.test` | Andrew Naguib | en | DEG-BTH Accepted | same three at 45% | Degree-program student / degree audit |
| `student10@spims.test` | Christine Wahba | **ar** | DEG-BTH Submitted | none | Second Arabic applicant |

Progress percents on enrollments are **hard-coded in the seeder**. They do not reflect completed lessons (there are none).

---

## 4. What the seeder actually creates

Verified 2026-09-06 after `migrate:fresh --seed`:

| Entity | Count | Notes |
|---|---|---|
| Users | 18 | 1 super admin + 17 demo |
| Programs | 5 | `DIP-THEO`, `CERT-LIT`, `DEG-BTH`, `CERT-BIB`, plus sample `DEMO-DIP` |
| Courses | 13 | 12 demo + `DEMO101` |
| Offerings | 14 | 8 Fall Open cohort + 1 ET101 self-paced + 4 Spring Draft + 1 sample |
| Weeks | 18 | 2 per Fall offering, 1 self-paced module — **0 content items** |
| Application forms | 4 | “Why join?” + “Parish name” — **0 field answers** |
| Applications | 10 | All statuses represented |
| Student programs | 4 | The four Accepted students |
| Enrollments | 12 | 11 Enrolled + 1 Waitlisted |
| Invoices / payments / wallets | **0** | |
| Assessments / questions / assignments | **0** | |
| Announcements / notifications | **0** | |
| Live sessions / class sessions / attendance | **0** | |
| Credentials / academic records | **0** | |
| Discussion posts | **0** | |
| Gradebook components | **0** | |

Curriculum (demo programs):

| Code | Name | Type |
|---|---|---|
| `DIP-THEO` | Diploma in Theology | Diploma |
| `CERT-LIT` | Certificate in Liturgics | Certificate |
| `DEG-BTH` | Bachelor of Theology | Degree |
| `CERT-BIB` | Certificate in Biblical Studies | Certificate (no students attached) |

Fall 2026/2027 Open offerings (instructor / TA):

| Course | Seats | Instructor | TA |
|---|---|---|---|
| TH101 Introduction to Theology | 20 | ins1 | ta1 |
| BI101 Old Testament Survey | 25 | ins2 | ta1 |
| BI102 New Testament Survey | 30 | ins1 | ta1 |
| LI101 Coptic Liturgy Basics | 35 | ins2 | — |
| CH101 Church History I | 40 | ins1 | — |
| ET101 Christian Ethics (cohort) | 45 | ins2 | — |
| FREE1 Open Orientation (Free) | 50 | ins1 | — |
| TH201 Patristics I | 55 | ins2 | — |

Prices are integer minor units (e.g. TH101 = USD 15000 / EGP 75000 → $150.00 / EGP 750.00). `ET101` and `FREE1` are free / standalone.

---

## 5. Client walkthrough (use this script)

Do this on **staging** after a fresh seed, or locally after the hand-entry in §6.

### 5.1 Fifteen-minute “what is this product?”

1. **Public catalog** (logged out) → `/catalog`. Point at theological course titles and USD/EGP prices.
2. **Student** `student1@spims.test` → dashboard, `/applications` (Accepted), `/enrollments` (one waitlisted), `/degree-audit/{id}` from the enrollments page, language switcher if you also open `student6` (Arabic) or `student4` (French).
3. **Admin** `adm@spims.test` → `/admin/applications`. Show Submitted / Under review / Waitlisted / Rejected without leaving the queue. Decide on `student3` if you want a live matriculation.
4. **Academic** `aca@spims.test` → `/admin/programs` → DIP-THEO → attached courses. `/admin/offerings` → Fall vs Spring Draft. `/admin/semesters`.
5. **Instructor** `ins1@spims.test` → `/teach`. **Do not click into an offering until the teach-500 is fixed**; use `/teach/{id}/attendance` or `/admin/offerings/{id}` as Academic instead.
6. **Finance** `fin@spims.test` → explain the four-bucket wallet and dual currency. The page will be empty until §6 invoices exist.
7. **Super admin** (optional, internal only) → `/roles-hub` to show permission matrix; `/superadmin/audit`.

### 5.2 What not to promise in that meeting

- “The student can watch Week 1 video” — no `ContentItem` rows.
- “Here is a paid invoice / receipt” — no finance rows.
- “Here is last week’s attendance” — no `class_sessions`.
- “Here is a certificate PDF” — credentials are HTML placeholders even when issued.
- “The instructor workspace” — `/teach/{offering}` 500s as of this review.
- Live Zoom — joins a mock URL unless Zoom keys are in `.env`.
- Card payments — mock auto-complete unless real gateway keys are set.

---

## 6. How to add dummy data by hand (no deploy)

Do this once on staging (or locally), then **do not** `migrate:fresh` or you will wipe it. Work as the role named.

### 6.1 Lessons a student can open (Academic or Super Admin)

1. Log in as `aca@spims.test`.
2. `/admin/offerings` → TH101.
3. Add two content items on Week 1, for example:
   - type **Text** or **Reading**, title “Lecture 1 — The Rule of Faith”, paste a short paragraph.
   - type **Video**, title “Welcome”, Vimeo id of any public video the school owns (or skip video).
4. Repeat one item on Week 2.
5. Log in as `student1@spims.test` → `/enrollments` → open TH101 (`/learn/{id}` or `/courses/{id}`) → mark the text item complete.

Until content exists, both players render empty weeks.

### 6.2 A quiz the student can sit (Academic)

1. `/admin/assessments` (banks) → create a bank “TH101 Week 1”.
2. Add 3–5 questions: one MCQ single, one true/false, one short answer. (The runner UI does **not** yet render matching / ordering / file-upload well.)
3. Create an assessment on the TH101 offering: released, open window now → +1 week, time limit 15 minutes, attach those questions.
4. Optionally add a gradebook component of kind Exam on `/admin/offerings/{id}/gradebook` and seed from a template if one exists.
5. As `student1`: start the assessment from the learn item or `/assessments/{id}`.
6. As `ins1` or `aca`: grade any essay; lock grades only when you intend to post academic records.

### 6.3 Attendance a registrar can export (Instructor)

1. After the teach-show 500 is fixed, `/teach/{TH101}` → Attendance; until then open `/teach/{TH101}/attendance`.
2. Open a class session (in-person is fine — no Zoom required).
3. Mark `student1` Present, `student6` Late, `student7` Absent, excuse one.
4. As `student1` try `/attendance` history and, if you issued a code, `/attendance/check-in`.
5. As `aca` open `/admin/attendance/report` and export CSV.

### 6.4 Money a bursar can show (Finance + a student)

Invoices are created **when a student registers**, not by the seeder. Two options:

**Option A — register a new course as the student**

1. `student1@spims.test` → `/enrollments`.
2. Register into `FREE1` (stays paid automatically) and into `CH101` or `LI101` (creates an unpaid invoice at the offering price).
3. `/finance` → open the invoice → checkout. With `PAYMENTS_MOCK_AUTO_COMPLETE=true` the gateway completes immediately. Wallet will still be empty unless you credit it first.
4. `fin@spims.test` → `/admin/finance` should now list the invoice. Record a **manual** cash payment on another invoice to show verify-flow.

**Option B — admin manual invoice**

1. `fin@spims.test` → `/admin/finance` → create invoice (needs a student ULID from `/admin/users` if the form is still ULID-based).
2. Record manual payment → verify.
3. Grant points via the points form so `/finance` for that student shows a wallet balance.

Donations: `/donate` as any authenticated user (permission `finance.donate`). Completes immediately in mock mode.

### 6.5 An announcement students see (Instructor)

1. `ins1@spims.test` → `/teach` → (once show works) Announcements tab, or use Academic tools if you add a draft via API.
2. Publish to the TH101 offering.
3. `student1` → `/announcements` and the dashboard banner.

Until then every role’s inbox is empty.

### 6.6 A live session (Academic)

1. `aca@spims.test` → `/admin/offerings/{TH101}/live` → schedule in the next hour.
2. Without Zoom keys the join URL is a mock. With keys, `student1` → `/live` → Join when the window opens.

### 6.7 A credential to verify in a browser (Academic)

1. Only after a student has **academic records** (gradebook lock) or you issue a standalone certificate.
2. `aca@spims.test` → `/admin/credentials` → issue (you will need the student ULID).
3. Open the public `/verify/{token}` in a private window — this is the employer story.
4. Tell the client the download is HTML today, PDF is S4.

### 6.8 Admissions live decision (Admin)

1. `adm@spims.test` → `/admin/applications` → `student3` (Under review) → Accept.
2. That creates a `student_programs` row. The new student can then register on `/enrollments`.
3. Rejecting or waitlisting `student10` shows the other two outcomes.

### 6.9 Arabic / French (any user)

Top-right locale switch, or log in as `ins2` / `student6` (ar) or `student4` (fr). **Course titles stay English** — there are no `translations` rows. The demo is chrome/i18n, not localized curriculum.

---

## 7. How to put dummy data into the seeder (for the next client reset)

Hand-entry dies on the next `migrate:fresh`. To make staging resettable, extend `database/seeders/DemoDataSeeder.php` **after** the existing enrollment block. Keep using the same services the UI uses so audit rows and invariants stay honest.

Suggested additions, in this order:

1. **Content** — for TH101 and BI101 Week 1, create 2–3 `ContentItem` rows (TEXT + READING). Optional `vimeo_id` only if a real video is licensed.
2. **Question bank + released quiz** on TH101 via `QuestionBankService` + `AssessmentService::release`.
3. **Gradebook** — `GradebookService::seedFromTemplate` or `addComponent` for Exam + Attendance on TH101.
4. **Invoices** — call `InvoiceService::createForEnrollment` for each paid enrollment (skip `is_free`). Optionally `PaymentService::recordManual` + `verifyManual` on student9 so finance is not empty.
5. **Wallet** — `WalletService::credit` a small EGP balance on student1 so split-pay is visible.
6. **Class session + entries** — `AttendanceService` open session on TH101, mark the three enrolled students differently.
7. **Announcement** — `AnnouncementService::draft` + `publish` from `ins1` to TH101.
8. **Live session** — `LiveSessionService::schedule` one future hour (mock Zoom is fine).
9. **Discussion** — `DiscussionService::createThread` + one student post.
10. **Staff dual user** — attach `dual@spims.test` as Instructor on FREE1 and enroll them in ET101 self-paced so the dual-role story works.
11. **Application answers** — `ApplicationFieldValue` rows for “Why join?” so review screens are not blank.
12. **One locked grade + credential** — only if you also seed attempts/scores; otherwise skip. A hollow certificate is worse than none.

Rules for that work (same as the rest of the repo):

- Mutations go through services + `AuditLogWriter::withAudit()`.
- Money is integer minor units.
- Staff an instructor with `OfferingStaff` before they act on an offering.
- Add a feature test that `DemoDataSeeder` creates at least one content item, one invoice, and one class session, so a future edit cannot silently empty the demo.

---

## 8. Staging vs production

| Environment | Seed demo? | Who may log in |
|---|---|---|
| Local | Yes (`SEED_DEMO_DATA=true`) | Developers + rehearsal |
| Staging | Yes | School leadership for UAT |
| Production | **No** | Set `SEED_DEMO_DATA=false`. Create real staff in `/admin/users`. Super admin only via `SUPERADMIN_*` env |

Never reuse `Spims@Test2026!` in production. Rotate `SUPERADMIN_PASSWORD` on first deploy.

---

## 9. API tokens (mobile / Postman)

```http
POST /api/v1/login
Content-Type: application/json
Accept: application/json

{"email":"student1@spims.test","password":"Spims@Test2026!"}
```

Response shape: `{ "data": { "token", "token_type", "user" } }`. Send `Authorization: Bearer {token}` and `Accept-Language: ar` to see Arabic strings.

What exists today: `me`, `branding`, announcements, notifications, notification settings, student attendance, teach attendance. Learn / grades / enroll / pay APIs are not shipped yet (roadmap S6).
