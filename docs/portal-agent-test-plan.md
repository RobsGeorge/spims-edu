# Portal + demo agent test plan

This is the playbook **Cursor agents** use to verify the public demo console and the whole SPIMS web portal. Humans can follow it too. It is not a PHPUnit replacement — Wave 0 is the automated gate; Waves 1–8 are browser (or curl) walks against a seeded app.

Account inventory and what the seeder creates: [`demo-accounts.md`](demo-accounts.md).  
Persona slugs and landings: `App\Support\DemoPersonas`.

---

## 0. How to run this as agents

### 0.1 Roles

| Agent | Job |
|---|---|
| **Coordinator** | Seed once, assign waves, merge reports, file failures. Does **not** click Reset while others run. |
| **Wave agent** | Owns one wave. Enters via `/demo` (never types a password). Walks every case in that wave. Writes a report. |

Spawn one wave agent per wave after Wave 0 is green. Waves **1–7 may run in parallel** on the same shared DB if they skip cases marked **MUTATING**. Run all **MUTATING** cases in a single agent at the end (Wave M), then Reset.

If the environment is disposable (local sqlite / dedicated staging), every agent may mutate freely and Reset when done.

### 0.2 Hard rules

1. **Never render, type, or assert the demo password** (`DemoDataSeeder::PASSWORD`). Enter is one-click. Fail any page that shows it.
2. **Never enter or advertise the Super Admin.** `/demo` must not list `SUPERADMIN_EMAIL`.
3. **Never `migrate:fresh`** on a shared or production database. Seed/Reset re-run `DemoDataSeeder` only.
4. **Authorization is permission keys**, not role-name string checks. A 403 for a persona that should have the page is a fail; a 200 on a page they must not see is a fail.
5. **Money on screen is formatted currency**, never a raw float from a ledger.
6. **RTL-first:** Arabic cases must have `html[dir=rtl]` and readable Arabic chrome (IBM Plex Sans Arabic).
7. Do not promise certificates, live Zoom, or card-gateway success. Those are non-goals (see §11).

### 0.3 Environment

```bash
# Disposable local (preferred for mutating waves)
unset $(env | grep '^DB_' | cut -d= -f1)
# .env: DB_CONNECTION=sqlite, SEED_DEMO_DATA=true, DEMO_CONSOLE=true
php artisan migrate:fresh --seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

Base URL: `http://127.0.0.1:8000` unless the coordinator says otherwise.

| Flag | Required for this plan |
|---|---|
| `DEMO_CONSOLE=true` | Waves 1 and M |
| `SEED_DEMO_DATA=true` | All waves |
| `DEMO_CONSOLE=false` | Case D0 only (kill switch), on a **separate** process or `config()` override |

PHPUnit already sets `DEMO_CONSOLE=false` and `SEED_DEMO_DATA=false`. Do not use phpunit.xml env for browser waves.

### 0.4 How to enter a persona (every UI wave)

1. Open `/demo` as a guest (or while logged in — Enter switches).
2. Click **Enter as {First}** on the tile. Do **not** open `/login` and paste a password.
3. Expect a 302 to the landing in §1.2, then a **200** (not Ignition / 500).
4. Confirm the header user name matches the persona.
5. Confirm the locale cookie matches the persona (`ar` / `en` / `fr`).

Resolve TH101 (and other course codes) only after seed:

```text
GET /demo → Enter as John → Location should be /learn/{ulid}
GET /demo → Enter as Mina → Location should be /teach/{ulid}
```

If the offering is missing, landings fall back to `/dashboard` or `/teach`. That is a **seed fail**, not a portal fail.

### 0.5 Pass / fail / blocked

| Result | Meaning |
|---|---|
| **PASS** | Steps completed; expected text/status/dir observed |
| **FAIL** | Wrong status, missing chrome, password leak, 500, or wrong persona |
| **BLOCKED** | Environment (DB, server, `DEMO_CONSOLE` off, missing seed) — not a product defect until reproduced on a seeded sqlite |

A 500 on a listed route is always **FAIL** (or **BLOCKED** if the server is pointed at the wrong database — confirm `php artisan tinker --execute="echo config('database.default');"`).

### 0.6 Artifacts (each wave agent)

Write under `/opt/cursor/artifacts/`:

- `{wave}-report.md` — table of case ID → PASS/FAIL/BLOCKED + one-line evidence
- Screenshots of every **FAIL** and of the first **PASS** in the wave
- One video per wave if `computerUse` is available (start at the first click, stop after the last assertion)

Do not upload Ignition / 500 screenshots as “success” artifacts.

### 0.7 Report template (paste into `{wave}-report.md`)

```md
# Wave N report
Base URL:
DB driver:
Seed present (student1 exists): yes/no
Case | Result | Evidence
D1 | PASS | …
```

---

## 1. Personas and landings

| Slug | Email | Name | Locale | Landing after Enter | Sidebar must include |
|---|---|---|---|---|---|
| `student1` | student1@spims.test | John Student | en | `/learn/{TH101}` | Home, Learning, Finance |
| `student6` | student6@spims.test | Hannah Rizk | ar | `/dashboard` | Home, Learning, Finance; `dir=rtl` |
| `ins1` | ins1@spims.test | Mina Instructor | en | `/teach/{TH101}` | Home, Learning, Teach, Finance |
| `aca` | aca@spims.test | Academic Dean | en | `/admin/programs` | Home, Learning, Academic, Finance |
| `adm` | adm@spims.test | Admin Office | en | `/admin/applications` | Home, Learning, Admin, Finance |
| `fin` | fin@spims.test | Finance Bursar | en | `/admin/finance` | Home, Learning, Finance |
| `ta1` | ta1@spims.test | Yousef Assistant | en | `/teach` | Home, Learning, Teach, Finance |
| `dual` | dual@spims.test | Dual Role | en | `/teach` | Home, Learning, Teach, Finance |
| `ins2` | ins2@spims.test | Mariana Teacher | ar | `/teach` | Teach; `dir=rtl` |
| `student2`–`student5`, `student7`–`student10` | (see demo-accounts) | | | `/dashboard` | Smoke only (Wave 6) |

**Must never appear on `/demo`:** Super Admin email, Super Admin tile, demo password.

---

## Wave 0 — Automated gate (coordinator)

Run before any browser wave. Unset `DB_*` so PHPUnit uses sqlite memory.

```bash
unset $(env | grep '^DB_' | cut -d= -f1)
./vendor/bin/phpunit --no-coverage \
  tests/Feature/Demo/DemoConsoleTest.php \
  tests/Feature/Smoke/HomePageTest.php \
  tests/Feature/Database/DemoDataSeederTest.php \
  tests/Feature/Auth/LoginFlowTest.php
```

Optional portal UI suites (already exist; run if time allows):

```bash
./vendor/bin/phpunit --no-coverage --testsuite Portal,StaffUi
./vendor/bin/phpunit --no-coverage \
  tests/Feature/Feedback/StudentSurveyUiTest.php \
  tests/Feature/Events/StudentEventUiTest.php \
  tests/Feature/LiveQuiz/StudentLiveQuizWebTest.php \
  tests/Feature/Projects/StudentProjectUiTest.php \
  tests/Feature/Completion/StudentCompletionWebTest.php
```

| ID | Expect |
|---|---|
| A0 | Demo + home + seeder + login tests **OK** |
| A1 | No test output contains the demo password |

**FAIL** if any test errors. Do not start UI waves.

---

## Wave 1 — Demo console

Guest chrome. `DEMO_CONSOLE=true`.

| ID | Mut | Steps | Expect |
|---|---|---|---|
| D0 | | On a process with `DEMO_CONSOLE=false` (or skip if you cannot isolate): `GET /` and `GET /demo` | Home has **no** “Try a demo”; `/demo` is **404**. Default env for other cases is console **on**. |
| D1 | | `GET /` | 200. Buttons: Create account, Sign in, **Try a demo**. Navbar **Demo**. No password string. |
| D2 | | Click **Try a demo** | `/demo`, heading “Walk through SPIMS” (or locale). Seed + Reset visible. Featured tiles: John, Hannah, Mina, Academic, Admin, Finance, Yousef, Dual. More personas section present. Emails visible; password absent. |
| D3 | | Open Seed modal, Cancel | Confirm dialog; Cancel closes; still guest. |
| D4 | | Open Reset modal, Cancel | Confirm dialog; Cancel closes; still guest. |
| D5 | M | Confirm **Seed demo data** | Flash “Demo data is ready.” `student1@spims.test` still enterable. |
| D6 | | Enter as **John** | 302 → `/learn/{TH101}` 200. Header **John**. Password absent. |
| D7 | | From an authenticated session, `/demo` → Enter as **Mina** | Session switches. `/teach/{TH101}` 200. Header **Mina**. Not John. |
| D8 | | `POST /demo/enter/superadmin` and `/demo/enter/nobody` (curl, CSRF from `/demo`) | **404**. Still not Super Admin. |
| D9 | | Locale → العربية on `/demo` | `dir="rtl"`. Heading `تعرّف على سبيمس`. Enter labels Arabic. |
| D10 | | Locale → Français on `/demo` | Heading `Parcourir SPIMS`. |
| D11 | M | Confirm **Reset demo data** | Flash reset. John still enterable. Shared-DB copy still on the page. |

Selectors: `form#demoSeedForm`, `form#demoResetForm`, `form[action*="/demo/enter/student1"]`, `#demoSeedModal`, `#demoResetModal`.

---

## Wave 2 — Public guest surfaces

Logged out. Do not Enter.

| ID | Steps | Expect |
|---|---|---|
| G1 | `/` | Landing 200, liturgical panel, locale switcher. |
| G2 | `/catalog` | Course cards including **TH101**. Guest can open a preview. |
| G3 | Open TH101 preview from catalog | `/offerings/{id}/preview` 200. Title/code visible. |
| G4 | `/register` | First name, last name, email, locale. No demo password. |
| G5 | `/login` | Email + password fields. Demo CTA not required here. |
| G6 | `/health` and `/up` | 200. |
| G7 | `/api/branding` | JSON `siteName`, `tokens`. |
| G8 | Viewport 390×844 on `/` and `/catalog` | No horizontal overflow. CTAs wrap. |

---

## Wave 3 — Student portal (`student1`, then `student6`)

Enter as John. Then repeat RTL checks as Hannah (S20–S22).

Resolve `{TH101}` from the first learn URL.

| ID | Mut | Path / action | Expect |
|---|---|---|---|
| S1 | | Landing after Enter | `/learn/{TH101}` 200. **TH101 — Introduction to Theology**. Week 1 items (Welcome / reading). Week 2 may be locked. |
| S2 | | Dashboard `/dashboard` | **Hello, John**. Courses include TH101 + BI101. Wallet chips (EGP money). Announcement banner if present. |
| S3 | | `/hubs/learning` | Tiles: catalog, grades, applications, enrollments, projects, live, live quiz, events, attendance, surveys, finance, transcript, settings, notifications, announcements. Each tile **200**. |
| S4 | | `/courses/{TH101}` (player) | Same offering; items open. |
| S5 | | Open a Week 1 TEXT item `/learn/{TH101}/items/{id}` | 200, body text, not 403. |
| S6 | | `/announcements` | “Week 1 is open” (or seeded announcement). Show page 200. |
| S7 | | `/finance` | Open invoice(s). Amounts look like currency, not raw minor units with no formatting. EGP wallet **50.00** class (5000 minor). |
| S8 | | Open one invoice | `/finance/invoices/{id}` 200. |
| S9 | | `/attendance` | History shows **Present** on TH101 Week 1. |
| S10 | | `/enrollments` | TH101 + BI101 enrolled; BI102 waitlisted. |
| S11 | | `/applications` | DIP-THEO Accepted. |
| S12 | | `/grades` | 200. Released items only; no crash if empty. |
| S13 | | `/surveys` | TH101 Week 1 feedback (if seeded on this branch) **or** empty state — not 500. |
| S14 | M | If a published survey exists: open + submit once | Success / already-submitted; no password. |
| S15 | | `/events` | Chapel vigil (if seeded) **or** empty. 200. |
| S16 | M | If event exists: reserve | Reserved; `/events/mine` shows it. |
| S17 | | `/live-quiz/join` | Join form 200. Do not require a live lobby. |
| S18 | | `/offerings/{TH101}/projects` or learning-hub Projects | Team Alpha / empty state. 200. |
| S19 | | `/settings`, `/notifications`, `/transcript`, completion `/offerings/{TH101}/completion` | All 200. Transcript/credentials may be empty (not seeded). |
| S20 | | Enter as **Hannah**. Dashboard | `dir=rtl`. Arabic chrome. Name Hannah. |
| S21 | | Hannah `/learn/{TH101}` and `/attendance` | 200. Late on Week 1 (attendance). |
| S22 | | 390×844 dashboard + learn as Hannah | Bottom nav visible; no overflow; RTL. |

**Sidebar must not show** Teach, Academic, Admin, Superadmin for John/Hannah.

---

## Wave 4 — Teach (`ins1`, then `ta1`)

Enter as Mina. `{TH101}` from the teach landing.

| ID | Mut | Path / tab | Expect |
|---|---|---|---|
| T1 | | Landing | `/teach/{TH101}` 200. Course code TH101. Workspace tabs present. |
| T2 | | `/teach` index | TH101 (and other staffed offerings: BI102, CH101, FREE1) listed. |
| T3 | | Tab **content** | Week 1 items visible. |
| T4 | | Tab **assessments** `/teach/{TH101}?tab=assessments` | Quiz exists; attempts link 200. |
| T5 | | Tab **assignments** `/teach/{TH101}/assignments` | 200. |
| T6 | | Tab **gradebook** `?tab=gradebook` | Components (Exam / Attendance). 200. |
| T7 | | `/teach/{TH101}/live` | Seeded live session. 200. |
| T8 | | `/teach/{TH101}/attendance` | Roster; student1 Present, student6 Late, student7 excused. |
| T9 | | `/teach/{TH101}/discussions` | Board 200. Grade control present for instructor. |
| T10 | | `?tab=announcements` | Published announcement. 200. |
| T11 | | `?tab=roster` | Enrolled names include John, Hannah. |
| T12 | | `/teach/{TH101}/students/{student1}` | Dossier 200. |
| T13 | | `/teach/{TH101}/completion` | Cohort completion 200. Close control is confirm-gated if shown. |
| T14 | | `/teach/{TH101}/projects` | 200. Team project if seeded. |
| T15 | | `/teach/{TH101}/live-quiz` | 200. Lobby / create chrome. Join code may show here — that is OK (not the account password). |
| T16 | | `/teach/{TH101}/surveys` | 200. |
| T17 | | Enter as **Yousef** (`ta1`) → `/teach` → open TH101 | 200. TA can see roster/attendance. Must **not** see grade-lock / offering-close if those are instructor-only (403 or hidden). |
| T18 | | As Mina, open `/admin/programs` | **403** or hidden in nav (no Academic hub). |
| T19 | | 390×844 teach show | Tabs scroll; no overflow. |

---

## Wave 5 — Academic, Admin, Finance

Three personas. Can be three sub-agents if they avoid MUTATING.

### 5.1 Academic (`aca`)

| ID | Path | Expect |
|---|---|---|
| C1 | Landing `/admin/programs` | DIP-THEO, CERT-LIT, DEG-BTH listed. |
| C2 | Open DIP-THEO | Courses include TH101. |
| C3 | `/admin/courses` | TH101, BI101, … 200. |
| C4 | `/admin/offerings` | Fall **Open** vs Spring **Draft** visible. |
| C5 | `/hubs/academic` | Every tile opens 200: programs, courses, offerings, templates, semesters, credentials, grading schemes, translations, attendance policy, communications, email templates, certificate templates, surveys. |
| C6 | `/admin/users` | **403** or no Admin users tile (academic is not office admin). |

### 5.2 Admin (`adm`)

| ID | Path | Expect |
|---|---|---|
| O1 | Landing `/admin/applications` | Queue includes Accepted (John), Under review (Mark), plus other statuses. student1/student3 show “Why join?” / “Parish name” when opened. |
| O2 | `/admin/users` | Demo users listed. 200. |
| O3 | `/hubs/admin` | Tiles 200: users, enrollments, theme, application forms, applications, communications, events. |
| O4 | `/admin/theme` | Theme editor 200. Do **not** save over production branding on a shared host. |
| O5 | `/admin/programs` | **403** or hidden (not academic). |
| O6 | `/admin/finance` | **403** or hidden. |

### 5.3 Finance (`fin`)

| ID | Path | Expect |
|---|---|---|
| F1 | Landing `/admin/finance` | Invoice list. student9 first invoice **paid**. |
| F2 | `/hubs/finance` | Student finance + admin finance + reports tiles. All 200. |
| F3 | `/finance` (own) | 200 (bursar may have no student invoices). |
| F4 | `/admin/applications` | **403** or hidden. |
| F5 | Money on admin finance | Formatted; no `$150.00` derived from a float in HTML comments / debug. |

---

## Wave 6 — Dual + remaining persona smoke

| ID | Persona | Expect after Enter |
|---|---|---|
| P1 | `dual` | `/teach` 200. Can open FREE1 teach. `/learn` or enrollments show ET101. Both Teach + Learning in nav. |
| P2 | `ins2` | `dir=rtl`. `/teach` 200. BI101 staffed. |
| P3 | `student2` | Dashboard; applications show DIP-THEO Submitted. No TH101 enrollment. |
| P4 | `student3` | Application Under review. |
| P5 | `student4` | Locale **fr** cookie / French chrome. Waitlisted application. |
| P6 | `student5` | Rejected application visible. |
| P7 | `student7` | Enrolled; attendance excused. |
| P8 | `student8` | Draft application. |
| P9 | `student9` | Enrolled; finance shows paid invoice. |
| P10 | `student10` | `dir=rtl`; DEG-BTH Submitted. |

Each: 200, correct name in header, password absent, no Super Admin chrome.

---

## Wave 7 — Cross-cutting chrome

Use `student1` unless noted.

| ID | Steps | Expect |
|---|---|---|
| X1 | Theme selector: light / dark / system | Class `theme-light` / `theme-dark` / `theme-system` on `<body>`. No flash of unstyled error. |
| X2 | Locale en → ar → fr → en on dashboard | `dir` flips; translated nav; no mixed leftover English-only crash. |
| X3 | Bottom nav at 390×844 | Home, Learning, Catalog (or Teach for ins1), Finance, More. Targets 200. |
| X4 | Skip link | `#main-content` exists; skip link in DOM. |
| X5 | `/catalog` while logged in as John | 200. Interest / enroll chrome does not 500. |
| X6 | As John, `GET /teach` | 403 or empty/hidden — not Mina’s hub. |
| X7 | As John, `GET /admin/users` | 403. |
| X8 | As John, `GET /superadmin` | 403. |
| X9 | As Mina, `GET /admin/finance` | 403. |
| X10 | Guest `GET /dashboard` | Redirect to login. |
| X11 | `/foundation/demo` as guest | 401/redirect — this is **not** the trial console. |

---

## Wave 8 — Super Admin (optional, local only)

**Skip on production / school-domain trial.** Use only on disposable local after `migrate:fresh --seed`.

Login via `/login` with `SUPERADMIN_EMAIL` from `.env` (not `/demo`).

| ID | Path | Expect |
|---|---|---|
| U1 | `/superadmin` | Shield console tiles. |
| U2 | `/roles` | Permission matrix. Do not save unless this DB is disposable. |
| U3 | `/superadmin/audit` | Recent `auth.demo_enter` / `demo.refresh` rows if demo was used. |
| U4 | `/superadmin/security`, observability, scheduled-tasks, system-tests | 200. |
| U5 | Confirm `/demo` still does **not** list this account. | |

---

## Wave M — Mutating close-out (single agent, last)

Run only after parallel waves finish, or on a disposable DB.

| ID | Action | Expect |
|---|---|---|
| M1 | `/demo` Seed (confirm) | Flash ready. Featured Enter still works. |
| M2 | Enter John → submit survey if still open | Accepted once. |
| M3 | Reserve chapel event if still open | Appears on `/events/mine`. |
| M4 | Join Team Alpha if still open | Membership shown. |
| M5 | Start TH101 quiz if in window | Runner 200. **Do not** require a passing score. |
| M6 | `/demo` Reset (confirm) | Flash reset. Re-enter John. Seeded announcement / TH101 still there. |
| M7 | After Reset, HTML of `/demo` still has **no** password. | |

---

## 2. Existing PHPUnit map (do not re-implement)

Agents should **run** these, not rewrite them, unless a UI wave FAIL has no coverage.

| Surface | Suite / files |
|---|---|
| Demo console | `tests/Feature/Demo/DemoConsoleTest.php` |
| Seeder contents | `tests/Feature/Database/DemoDataSeederTest.php` |
| Home / RTL smoke | `tests/Feature/Smoke/HomePageTest.php` |
| Portal shell / teach show | `tests/Feature/Portal/*` |
| Staff teach UI | `tests/Feature/StaffUi/*` |
| Student surveys / events / live quiz / projects / completion | matching `tests/Feature/{Feedback,Events,LiveQuiz,Projects,Completion}/*Ui*` and `*Web*` |
| Scope (instructor cannot act on unstaffed offering) | `tests/Feature/Auth/ResourceScopeTest.php` |

---

## 3. Known non-goals (do not FAIL)

- Credential PDF / certificate download for seeded students (none issued).
- Real Zoom join (mock URL is OK).
- Real PayPal/Paymob capture (mock auto-complete in local).
- Course titles translated (no `translations` rows in the seeder).
- Progress % matching completed lessons (hard-coded in the seeder).
- `POST /foundation/demo` (audit-smoke; unrelated to `/demo`).
- Native apps / `/api/v1` contract (out of this plan). Use API suites separately.

---

## 4. Coordinator checklist

1. Confirm sqlite (or intended DB) and `php artisan serve`.
2. Wave 0 green.
3. Launch Waves 1–7 (skip MUTATING cases).
4. Merge reports. Any FAIL → fix or ticket with case ID + screenshot.
5. Wave M on disposable DB (or after others stop).
6. Wave 8 only if local and requested.
7. Final note: password never appeared; Super Admin never on `/demo`; no `migrate:fresh` on shared hosts.
