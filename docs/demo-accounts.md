# Demo accounts and client dummy data (SPIMS)

This is the playbook for showing SPIMS to school leadership. It covers who to log in as, what is already seeded, and how to reset staging with `migrate:fresh --seed`.

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

Open `http://localhost:8000` (or the school domain). Guests can also open **Try a demo** on the home page (`/demo`) to seed or reset walkthrough rows and sign in as a persona in one click. The shared password is never shown on that page.

| Flag | Default | Effect |
|---|---|---|
| `SEED_SAMPLE_DATA` | `true` | Tiny extra program `DEMO-DIP` / course `DEMO101` |
| `SEED_DEMO_DATA` | `true` | All accounts and curriculum below |
| `DEMO_CONSOLE` | `true` | Public `/demo` console (persona enter + re-seed). Set `false` to hide it |
| `SUPERADMIN_EMAIL` | `robeir.george@outlook.com` | Super-admin identity |
| `SUPERADMIN_PASSWORD` | `Spims@Dev2026!` | Super-admin password **only** |

`php artisan db:seed --class=DemoDataSeeder` alone is **not** enough on an empty database — it skips languages, grading scheme, theme, permissions, and the super admin. Prefer `migrate:fresh --seed`.

PHPUnit keeps `SEED_DEMO_DATA=false` by default. `tests/Feature/Database/DemoDataSeederTest.php` turns it on for that suite only.

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

| Email | Name | Role | Locale | What they can show today |
|---|---|---|---|---|
| `robeir.george@outlook.com` | George Robei | Super Admin | en | Every menu, Roles Hub, audit, health, theme |
| `adm@spims.test` | Admin Office | Administrative Admin | en | Users, admissions queue (10 applications, mixed status), semesters, branding |
| `aca@spims.test` | Academic Dean | Academic Admin | en | Programs, courses, offerings, gradebook, live admin, translations |
| `fin@spims.test` | Finance Bursar | Financial Admin | en | Finance hub with invoices; student9 has a verified cash payment |
| `ins1@spims.test` | Mina Instructor | Instructor | en | Teach TH101 (lessons, quiz, announcement, attendance, live). Also BI102, CH101, FREE1 |
| `ins2@spims.test` | Mariana Teacher | Instructor | **ar** | Teach BI101 (Week 1 lessons). Also LI101, ET101 cohort, TH201 |
| `ta1@spims.test` | Yousef Assistant | TA | en | Staffed on TH101, BI101, BI102. Cannot lock grades |
| `dual@spims.test` | Dual Role | Instructor + Student | en | Instructor on FREE1; enrolled in ET101 self-paced |

### Students (give these to the school to “be a student”)

| Email | Name | Locale | Application | Enrollments | Best use |
|---|---|---|---|---|---|
| `student1@spims.test` | John Student | en | DIP-THEO **Accepted** (answers filled) | TH101 + BI101 enrolled, BI102 **waitlisted** | Primary student walkthrough: lesson, announcement, invoice, attendance |
| `student2@spims.test` | Sara Habib | **ar** | DIP-THEO Submitted | none | Arabic applicant; admissions still in flight |
| `student3@spims.test` | Mark Shenouda | en | DIP-THEO Under review (answers filled) | none | Reviewer queue |
| `student4@spims.test` | Mary Guirguis | **fr** | DIP-THEO Waitlisted | none | French UI + waitlisted application |
| `student5@spims.test` | David Bishoy | en | DIP-THEO **Rejected** | none | Rejection state |
| `student6@spims.test` | Hannah Rizk | **ar** | CERT-LIT Accepted | TH101, BI101, BI102 | Arabic enrolled student; marked **Late** on TH101 Week 1 |
| `student7@spims.test` | Peter Atallah | en | CERT-LIT Accepted | same three | Marked **Absent** then **excused** on TH101 Week 1 |
| `student8@spims.test` | Rebecca Fawzy | en | CERT-LIT **Draft** | none | Unfinished application |
| `student9@spims.test` | Andrew Naguib | en | DEG-BTH Accepted | same three | Degree-program student; first invoice is **paid** (manual cash) |
| `student10@spims.test` | Christine Wahba | **ar** | DEG-BTH Submitted | none | Second Arabic applicant |

Progress percents on enrollments are still hard-coded in the seeder. They do not reflect completed lessons.

---

## 4. What the seeder actually creates

After `migrate:fresh --seed` with `SEED_DEMO_DATA=true`:

| Entity | Count | Notes |
|---|---|---|
| Users | 18 | 1 super admin + 17 demo |
| Programs | 5 | `DIP-THEO`, `CERT-LIT`, `DEG-BTH`, `CERT-BIB`, plus sample `DEMO-DIP` |
| Courses | 13 | 12 demo + `DEMO101` |
| Offerings | 14 | 8 Fall Open cohort + 1 ET101 self-paced + 4 Spring Draft + 1 sample |
| Weeks | 18 | 2 per Fall offering, 1 self-paced module |
| Content items | 6 | TH101 + BI101 Week 1: TEXT + READING (+ a third TEXT on each) |
| Question banks / questions | 1 / 4 | TH101 Week 1 bank: MCQ, T/F, short, numeric |
| Assessments | 1 | Released, in-window quiz on TH101, attached to the welcome item |
| Gradebook components | 2 | Exam + Attendance on TH101 |
| Application forms | 4 | “Why join?” + “Parish name” |
| Application field values | 4 | student1 and student3 |
| Applications | 10 | All statuses represented |
| Student programs | 4 | The four Accepted students |
| Enrollments | 13 | 12 from accepted students + dual on ET101 self-paced |
| Invoices | 12 | 11 non-free enrolled rows + dual’s free ET101 invoice; student9 first invoice is paid |
| Payments | 1 | Manual cash, verified, on student9 |
| Wallet accounts | 1 | student1 has EGP 50.00 money (`5000` minor) |
| Announcements | 1 | Published from ins1 to TH101 |
| Live sessions | 1 | TH101, scheduled in the next 24h (mock Zoom) |
| Class sessions / attendance | 1 / 3 | student1 Present, student6 Late, student7 Excused |
| Discussion posts | 2 | Instructor thread + student1 reply on TH101 |
| Credentials / academic records | 0 | Not seeded — no locked grades or honest certificate |

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
| FREE1 Open Orientation (Free) | 50 | ins1 + dual | — |
| TH201 Patristics I | 55 | ins2 | — |

Prices are integer minor units (e.g. TH101 = USD 15000 / EGP 75000 → $150.00 / EGP 750.00). `ET101` and `FREE1` are free / standalone.

---

## 5. Client walkthrough (use this script)

Do this on **staging** after a fresh seed.

### 5.1 Fifteen-minute “what is this product?”

1. **Public catalog** (logged out) → `/catalog`.
2. **Student** `student1@spims.test` → `/learn/{TH101}` (Week 1 lessons), `/announcements`, `/finance` (open invoices + EGP wallet), `/attendance` (Present on Week 1).
3. **Admin** `adm@spims.test` → `/admin/applications`. student1/student3 have “Why join?” / “Parish name” answers.
4. **Academic** `aca@spims.test` → `/admin/programs` → DIP-THEO. `/admin/offerings` → Fall vs Spring Draft.
5. **Instructor** `ins1@spims.test` → `/teach/{TH101}` (content, roster, announcements). Attendance at `/teach/{TH101}/attendance`.
6. **Finance** `fin@spims.test` → `/admin/finance` lists invoices; student9’s first invoice is paid.
7. **Dual** `dual@spims.test` → teach FREE1 and learn ET101 self-paced.

### 5.2 What not to promise in that meeting

- “Here is a certificate PDF” — credentials are not seeded (and downloads are HTML today; PDF is S4).
- Live Zoom — joins a mock URL unless Zoom keys are in `.env`.
- Card payments — mock auto-complete unless real gateway keys are set.
- Course titles stay English — there are no `translations` rows.

---

## 6. Extra dummy data by hand (optional)

The seeder now covers the classroom + money walkthrough. Use the admin/teach screens only if you want more weeks, another quiz, or a second live session. Do not `migrate:fresh` after hand-entry unless you intend to wipe it.

---

## 7. How dummy data is put into the seeder

`database/seeders/DemoDataSeeder.php` seeds catalog and enrollments, then calls the same services the UI uses for classroom and money rows: `OfferingService`, `QuestionBankService`, `AssessmentService`, `GradebookService`, `InvoiceService`, `PaymentService`, `WalletService`, `AttendanceService`, `AnnouncementService`, `LiveSessionService`, `DiscussionService`, `EnrollmentService`.

Rules for that work (same as the rest of the repo):

- Mutations go through services + `AuditLogWriter::withAudit()`.
- Money is integer minor units.
- Instructors are staffed on the offering before they act on it.
- `tests/Feature/Database/DemoDataSeederTest.php` asserts content, invoice, class session, announcement, and the dual-role account so a future edit cannot silently empty the demo.

Locked grades and credentials are **not** seeded. A hollow certificate is worse than none.

---

## 8. Staging vs production

| Environment | Seed demo? | Public `/demo` console |
|---|---|---|
| Local | Yes (`SEED_DEMO_DATA=true`) | Yes (`DEMO_CONSOLE=true`) |
| Staging | Yes | Yes — shared database; Reset restores walkthrough rows for everyone |
| Production | Yes for the school trial | Yes at first so leadership can try it on their domain. Set `DEMO_CONSOLE=false` when the trial should come down |

`GET /demo` is public. Seed and Reset re-run `DemoDataSeeder` only (not `migrate:fresh`). Enter signs the guest in as a `@spims.test` persona; the password is applied by the system and is never rendered.

Do not put the super-admin on the demo page. Rotate `SUPERADMIN_PASSWORD` on first deploy. Set `DEMO_CONSOLE=false` before treating production as a live school.

---

## 9. API tokens (mobile / Postman)

```http
POST /api/v1/login
Content-Type: application/json
Accept: application/json

{"email":"student1@spims.test","password":"Spims@Test2026!"}
```

Response shape: `{ "data": { "token", "token_type", "user" } }`. Send `Authorization: Bearer {token}` and `Accept-Language: ar` to see Arabic strings.
