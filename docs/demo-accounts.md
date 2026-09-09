# Demo accounts and client dummy data (SPIMS)

Playbook for showing SPIMS to school leadership: who to log in as, what is seeded, and how to reset. Prefer **https://demo.spims-edu.com** for client meetings (dedicated demo DB). Use staging only for engineering UAT. Never use production for demos.

Environment & seeder backlog: [demo-client-show-plan.md](demo-client-show-plan.md).  
Engineering review of the product itself: [system-code-review.md](system-code-review.md).

---

## 1. One-time setup

On a laptop or on the **demo** host:

```bash
cp .env.example .env
php artisan key:generate
# Confirm these two lines (they are the defaults):
# SEED_SAMPLE_DATA=true
# SEED_DEMO_DATA=true
php artisan migrate:fresh --seed
php artisan serve
```

| Host | URL | When to use |
|---|---|---|
| **Demo (preferred show)** | https://demo.spims-edu.com | Client walkthroughs & training |
| Local | http://localhost:8000 | Developer rehearsal |
| Staging | https://staging.spims-edu.com | Engineering UAT (may reset on deploy) |
| Production | https://spims-edu.com | Real school only — **no** demo seed |

| Flag | Default | Effect |
|---|---|---|
| `SEED_SAMPLE_DATA` | `true` | Tiny extra program `DEMO-DIP` / course `DEMO101` |
| `SEED_DEMO_DATA` | `true` | All accounts and curriculum below |
| `SUPERADMIN_EMAIL` | `robeir.george@outlook.com` | Super-admin identity |
| `SUPERADMIN_PASSWORD` | `Spims@Dev2026!` | Super-admin password **only** |

`php artisan db:seed --class=DemoDataSeeder` alone is **not** enough on an empty database — it skips languages, grading scheme, theme, permissions, and the super admin. Prefer `migrate:fresh --seed` (or the demo host’s `spims:demo-reset` if configured).

PHPUnit keeps `SEED_DEMO_DATA=false` by default. `tests/Feature/Database/DemoDataSeederTest.php` turns it on for that suite only.

---

## 2. Passwords

| Who | Password |
|---|---|
| Every `@spims.test` demo user | `Spims@Test2026!` |
| Super admin | `Spims@Dev2026!` (or whatever is in `.env`) |

Do not give clients the super-admin password on production. Demo/staging is the right place for a walkthrough.

---

## 3. Accounts — who to hand to whom

Use these in this order. Each row is a **persona**, not just a login.

### Staff (give these to the school team)

| Email | Name | Role | Locale | What they can show today |
|---|---|---|---|---|
| `robeir.george@outlook.com` | George Robei | Super Admin | en | Every menu, Roles Hub, audit, health, theme |
| `adm@spims.test` | Admin Office | Administrative Admin | en | Users, admissions queue (10 applications, mixed status), semesters, branding, published event |
| `aca@spims.test` | Academic Dean | Academic Admin | en | Programs, courses, offerings, gradebook, email templates, assessment templates, team projects, credentials (when PDF available) |
| `fin@spims.test` | Finance Bursar | Financial Admin | en | Finance hub with invoices; student9 has a verified cash payment |
| `ins1@spims.test` | Mina Instructor | Instructor | en | Teach TH101 (lessons, quiz, assignment grading queue, announcement, attendance, live, survey, live-quiz lobby). Also BI102 (Week 1), CH101, FREE1 |
| `ins2@spims.test` | Mariana Teacher | Instructor | **ar** | Teach BI101 and LI101 (Week 1 lessons). Also ET101 cohort, TH201 |
| `ta1@spims.test` | Yousef Assistant | TA | en | Staffed on TH101, BI101, BI102. Cannot lock grades |
| `dual@spims.test` | Dual Role | Instructor + Student | en | Instructor on FREE1; enrolled in ET101 self-paced |

### Students (give these to the school to “be a student”)

| Email | Name | Locale | Application | Enrollments | Best use |
|---|---|---|---|---|---|
| `student1@spims.test` | John Student | en | DIP-THEO **Accepted** (answers filled) | TH101 + BI101 enrolled (TH101 grades **locked**), BI102 **waitlisted** | Primary student walkthrough: lesson, assignment (graded), quiz attempt, announcement, invoice, attendance, join live-quiz lobby, team project, optional transcript verify |
| `student2@spims.test` | Sara Habib | **ar** | DIP-THEO Submitted | none | Arabic applicant; admissions still in flight |
| `student3@spims.test` | Mark Shenouda | en | DIP-THEO Under review (answers filled) | none | Reviewer queue |
| `student4@spims.test` | Mary Guirguis | **fr** | DIP-THEO Waitlisted | none | French UI + waitlisted application |
| `student5@spims.test` | David Bishoy | en | DIP-THEO **Rejected** | none | Rejection state |
| `student6@spims.test` | Hannah Rizk | **ar** | CERT-LIT Accepted | TH101, BI101, BI102 | Arabic enrolled student; marked **Late** on TH101 Week 1; pending assignment submission on TH101 |
| `student7@spims.test` | Peter Atallah | en | CERT-LIT Accepted | same three | Marked **Absent** then **excused** on TH101 Week 1 |
| `student8@spims.test` | Rebecca Fawzy | en | CERT-LIT **Draft** | none | Unfinished application |
| `student9@spims.test` | Andrew Naguib | en | DEG-BTH Accepted | same three | Degree-program student; first invoice is **paid** (manual cash) |
| `student10@spims.test` | Christine Wahba | **ar** | DEG-BTH Submitted | none | Second Arabic applicant |

Progress percents on enrollments are still hard-coded in the seeder. They do not reflect completed lessons.

---

## 4. What the seeder actually creates

After `migrate:fresh --seed` with `SEED_DEMO_DATA=true` (approximate; Phase D counts):

| Entity | Count | Notes |
|---|---|---|
| Users | 18 | 1 super admin + 17 demo |
| Programs | 5–6 | `DIP-THEO`, `CERT-LIT`, `DEG-BTH`, `CERT-BIB`, `DEG-DIAC`, plus sample `DEMO-DIP` when sample seed is on |
| Courses | 13 | 12 demo + `DEMO101` |
| Offerings | 14 | 8 Fall Open cohort + 1 ET101 self-paced + 4 Spring Draft + 1 sample |
| Weeks | 18 | 2 per Fall offering, 1 self-paced module |
| Content items | 15+ | TH101/BI101 Week 1 (3 each) + TH101 Week 1 **ASSIGNMENT** + TH101 Week 2 (4) + BI102/LI101 Week 1 (TEXT+READING each) |
| Question banks / questions | 1 / 4 | TH101 Week 1 bank: MCQ, T/F, short, numeric |
| Assessments | 1 | Released, in-window quiz on TH101, attached to the welcome item |
| Assessment attempts | 1+ | student1 submitted + graded on “TH101 Week 1 check” |
| Assignments / submissions | 1 / 2 | TH101 Week 1 reflection; student1 graded, student6 pending review |
| Gradebook components | 2 | Exam + Attendance on TH101 (grades submitted then **locked**) |
| Application forms | 4 | “Why join?” + “Parish name” |
| Application field values | 4 | student1 and student3 |
| Applications | 10+ | All statuses including Withdrawn |
| Student programs | 4 | The four Accepted students |
| Enrollments | 13 | 12 from accepted students + dual on ET101 self-paced; TH101 grades **Locked** |
| Invoices | 12 | 11 non-free enrolled rows + dual’s free ET101 invoice; student9 first invoice is paid |
| Payments | 1 | Manual cash, verified, on student9 |
| Wallet accounts | 1 | student1 has EGP 50.00 money (`5000` minor) |
| Announcements | 1 | Published from ins1 to TH101 |
| Live sessions | 1 | TH101, scheduled in the next 24h (mock Zoom) |
| Class sessions / attendance | 1 / 3 | student1 Present, student6 Late, student7 Excused |
| Discussion posts | 2 | Instructor thread + student1 reply on TH101 |
| Events | 1 | Published “Theology Orientation Day” with open seats |
| Feedback surveys | 1 | TH101 Week 1 Feedback (scale/text/single/multi), published |
| Live quizzes / sessions | 1 / 1 | Ready quiz + **Lobby** session with join code (no question timer running) |
| Email templates | 1 | Global `announcement.published` (en) for `/admin/email-templates` |
| Assessment templates | 1 | “Demo Standard Rollup” (Exam 50 / Assignments 30 / Attendance 20) |
| Team projects | 1 | TH101 group project; student1 is a member; deliverable due in the future |
| Credentials / academic records | best-effort | TH101 grades are locked (academic records posted). Transcript credential for student1 when PDF rendering is available |

Curriculum (demo programs):

| Code | Name | Type |
|---|---|---|
| `DIP-THEO` | Diploma in Theology | Diploma |
| `CERT-LIT` | Certificate in Liturgics | Certificate |
| `DEG-BTH` | Bachelor of Theology | Degree |
| `CERT-BIB` | Certificate in Biblical Studies | Certificate (no students attached) |
| `DEG-DIAC` | (diaconate track) | Degree with `enforce_year_sequence` |

Fall 2026/2027 Open offerings (instructor / TA):

| Course | Seats | Instructor | TA | Week 1 content |
|---|---|---|---|---|
| TH101 Introduction to Theology | 20 | ins1 | ta1 | Yes (full walkthrough) |
| BI101 Old Testament Survey | 25 | ins2 | ta1 | Yes |
| BI102 New Testament Survey | 30 | ins1 | ta1 | Yes (TEXT + READING) |
| LI101 Coptic Liturgy Basics | 35 | ins2 | — | Yes (TEXT + READING) |
| CH101 Church History I | 40 | ins1 | — | Empty weeks (ok for catalog) |
| ET101 Christian Ethics (cohort) | 45 | ins2 | — | Empty weeks |
| FREE1 Open Orientation (Free) | 50 | ins1 + dual | — | Empty weeks |
| TH201 Patristics I | 55 | ins2 | — | Empty weeks |

Prices are integer minor units (e.g. TH101 = USD 15000 / EGP 75000 → $150.00 / EGP 750.00). `ET101` and `FREE1` are free / standalone.

---

## 5. Client walkthrough (use this script)

Do this on **https://demo.spims-edu.com** after a fresh seed (or local/`staging` only if demo is unavailable).

### 5.1 Fifteen-minute “what is this product?”

1. **Public catalog** (logged out) → `/catalog`.
2. **Student** `student1@spims.test` → `/learn/{TH101}` (Week 1 lessons + reflection assignment), `/announcements`, `/finance` (open invoices + EGP wallet), `/attendance` (Present on Week 1), `/live-quiz/join` with the Lobby join code from teach, `/events`. Show the graded quiz attempt and assignment score on the grades surface.
3. **Admin** `adm@spims.test` → `/admin/applications`. student1/student3 have “Why join?” / “Parish name” answers. `/admin/events` for Orientation Day.
4. **Academic** `aca@spims.test` → `/admin/programs` → DIP-THEO. `/admin/offerings` → Fall vs Spring Draft. Gradebook for TH101 shows **locked** grades. `/admin/email-templates`, `/admin/assessment-templates`.
5. **Instructor** `ins1@spims.test` → `/teach/{TH101}` (content, roster, announcements, live-quiz Lobby). Assignment queue has student6 pending; student1 already graded. Also `/teach/{BI102}` has Week 1 content. Attendance at `/teach/{TH101}/attendance`.
6. **Instructor (ar)** `ins2@spims.test` → `/teach/{LI101}` Week 1 lessons (Arabic UI).
7. **Finance** `fin@spims.test` → `/admin/finance` lists invoices; student9’s first invoice is paid.
8. **Dual** `dual@spims.test` → teach FREE1 and learn ET101 self-paced.

### 5.2 What not to promise in that meeting

- Real Zoom — joins a mock URL unless Zoom keys are in `.env`.
- Card payments — mock auto-complete unless real gateway keys are set.
- Parent portal — not a product surface yet.
- Hollow “certificate PDF polish” — transcript credential is best-effort; verify only when `/verify/{token}` works after seed. Do not invent a PDF if issuance failed.
- Course titles may show ar/fr translations for seeded entities (TH101 / DIP-THEO); catalog UI locale is separate from title translations.
- Live-quiz is in **Lobby** only — launching a question starts a timer; do that live if you want, don’t leave a mid-question timer overnight.
- CH101 / ET101 / FREE1 / TH201 teach pages may still look thin (by design); use TH101, BI101, BI102, LI101 for content demos.

---

## 6. Extra dummy data by hand (optional)

The seeder covers the classroom + money + Phase D polish walkthrough. Use the admin/teach screens only if you want more weeks, another quiz, or a second live session. Do not `migrate:fresh` after hand-entry unless you intend to wipe it.

---

## 7. How dummy data is put into the seeder

`database/seeders/DemoDataSeeder.php` seeds catalog and enrollments, then calls the same services the UI uses for classroom and money rows: `OfferingService`, `QuestionBankService`, `AssessmentService`, `AssignmentService`, `AttemptService`, `GradebookService`, `InvoiceService`, `PaymentService`, `WalletService`, `AttendanceService`, `AnnouncementService`, `LiveSessionService`, `DiscussionService`, `EnrollmentService`, plus Phase B–D services (`LiveQuizHostService`, `EmailTemplateService`, `AssessmentTemplateService`, events, surveys, projects, credentials, advising, completion, translations, notifications).

Rules for that work (same as the rest of the repo):

- Mutations go through services + `AuditLogWriter::withAudit()`.
- Money is integer minor units.
- Instructors are staffed on the offering before they act on it.
- `tests/Feature/Database/DemoDataSeederTest.php` asserts content, invoice, class session, announcement, dual-role, Phase A assignment/quiz/lock, Phase B–C fixtures, live-quiz Lobby, email/assessment templates, and BI102/LI101 Week 1 so a future edit cannot silently empty the demo.

TH101 grades are submitted then locked via `GradebookService`. Transcript credentials remain best-effort when PDF rendering is unavailable.

---

## 8. Staging vs demo vs production

| Environment | Seed demo? | Who may log in |
|---|---|---|
| Local | Yes (`SEED_DEMO_DATA=true`) | Developers + rehearsal |
| **Demo** (`demo.spims-edu.com`) | Yes | School leadership / training — preferred client host |
| Staging | Yes | Engineering UAT (expect resets on deploy) |
| Production | **No** | Set `SEED_DEMO_DATA=false`. Create real staff in `/admin/users`. Super admin only via `SUPERADMIN_*` env |

Never reuse `Spims@Test2026!` in production. Rotate `SUPERADMIN_PASSWORD` on first deploy (and independently on demo).

**Day-before client visit:** on the demo host, run a controlled reset (`migrate:fresh --seed` or `spims:demo-reset`), smoke `/health`, log in as `student1` + `ins1`, confirm live-quiz Lobby join code is visible.

---

## 9. API tokens (mobile / Postman)

```http
POST /api/v1/login
Content-Type: application/json
Accept: application/json

{"email":"student1@spims.test","password":"Spims@Test2026!"}
```

Response shape: `{ "data": { "token", "token_type", "user" } }`. Send `Authorization: Bearer {token}` and `Accept-Language: ar` to see Arabic strings.
