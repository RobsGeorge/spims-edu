# Legacy import — use cases & expected results

A worked catalogue of legacy-import scenarios for **system administrators and Super Admins** who run
data migration from Populi and Canvas into SPIMS. Every case below was executed against a **freshly
seeded system** (the same demo dataset staging carries) through the real import pipeline — upload →
map → validate → dry run → commit — and its actual result compared against the behaviour the
[legacy data import plan](https://github.com/RobsGeorge/spims-edu/blob/main/docs/legacy-data-import-plan.md)
specifies. Use it to know, before you touch production data, exactly what each kind of row will do.

The input files for every case live in the repository under `docs/legacy-import-use-cases/`, grouped
by entity type. You can upload them as-is on a demo/staging environment to reproduce any row here.

## Test baseline

These cases assume a database seeded with `DemoDataSeeder` + `LegacyImportDemoSeeder` (i.e. a fresh
`php artisan migrate:fresh --seed`). That baseline provides:

- **Import sources**: `POPULI` (SIS) and `CANVAS` (LMS), with a starter A/B/C/F grade-conversion table on POPULI.
- **Programs (diplomas/degrees)**: `DIP-THEO`, `CERT-LIT`, `DEG-BTH`, `CERT-BIB`, `DEG-DIAC`.
- **A live course**: `TH101` (and others) with a real, open offering in the semester named **Fall**, carrying two gradebook components — **Exam** (70%) and **Attendance** (30%).
- **One native credential** already issued, serial `SPIMS-CRED-2026-00001`.
- **Login**: run imports as `adm@spims.test` (Administrative Admin); commit a BALANCE batch as `fin@spims.test` (Financial Admin). Password `Spims@Test2026!`.

Every dependent entity type (course result, balance, credential, mid-term) resolves its student by
the legacy id recorded when the **STUDENT** batch was committed. So the order is always: commit
students first, then everything that references them.

---

## 1. Students — old and new people (identity)

Files: `docs/legacy-import-use-cases/student/`

| ID | Scenario | Expected outcome | Result |
|---|---|---|---|
| UC-S1 | **Old alumnus, real email, valid diploma** (legacy 7001, DIP-THEO) | Created as a user, `status = ARCHIVED`, no password, the DIP-THEO program attached. No login, transcript only. | Confirmed — created, ARCHIVED, program attached. |
| UC-S2 | **Old alumnus, no email** (legacy 7002, CERT-BIB) | Warning `W_NO_EMAIL_ALUMNUS`; a synthetic `legacy.populi.7002@no-email.invalid` address is generated; created ARCHIVED, never emailable. | Confirmed — synthetic address `legacy.populi.7002@no-email.invalid`, status ARCHIVED. |
| UC-S3 | **New / currently-studying student, real email, valid degree** (legacy 7101, DEG-BTH) | Created `status = PENDING`, no password; one `import_account_claim` row queued (`QUEUED`). No invitation is sent by the commit itself. | Confirmed — PENDING, claim rows queued. |
| UC-S4 | **Active student, no email** (legacy 7109) | Hard row error `E_ACTIVE_NO_EMAIL` — the row is blocked, nothing is created. An active student with no reachable address cannot claim an account. | Confirmed — blocked with `E_ACTIVE_NO_EMAIL`. |
| UC-S5 | **Alumnus with an unknown program code** (legacy 7003, `ZZZ-NONE`) | Warning `W_UNKNOWN_PROGRAM_CODE`; the person is still created, but **no** program is attached. | Confirmed — warned, no program attached. |
| UC-S6 | **Re-importing the same file** (idempotency) | Rows link to the people already created instead of duplicating them: `create = 0`, `link = 2`. | Confirmed — 0 created, 2 linked. |

> **Operational note — a new person with a unique email is not guaranteed to import as a "create".**
> In UC-S1 the third row (legacy 7003, "Karas Shenouda") is **not** created on commit — it is routed
> to the **merge queue** instead, even though it carries a real, unique email. This is correct per
> the matching ladder (plan §6): rung 6 never auto-links a name-similarity match, and 7003 shares the
> surname "Shenouda" with the seeded user "Mark Shenouda". The consequence to plan for: **any incoming
> person who shares a surname with an existing user is queued for a human decision, not created** — so
> a registrar must actually work the *Identity review queue* (`/admin/imports/merges`) after every
> STUDENT commit, or those people are silently left un-imported. This is behaviour, not a defect, but
> it is easy to miss on a large file where only a handful of rows are diverted.

---

## 2. Course results — old courses and transfer credit

Files: `docs/legacy-import-use-cases/course-result/`

| ID | Scenario | Expected outcome | Result |
|---|---|---|---|
| UC-CR1 | **Result for a course not in the live catalogue** (legacy 7001, `OT-HIST-201`) | A shadow `Course` + shadow `CourseOffering` are find-or-created (archived, invisible to every native surface); an `AcademicRecord` is written with `counts_toward_gpa = false`. The live GPA never moves. | Confirmed — 1 shadow course, shadow offerings created; record isolated from GPA. |
| UC-CR2 | **Result whose code matches a live course** (legacy 7001, `TH101`) | The existing live `TH101` course is reused (not duplicated); a per-term shadow offering still carries the legacy result; still `counts_toward_gpa = false`. | Confirmed — live course reused; result isolated. |
| UC-CR3 | **Result for a student never imported** (legacy 9999) | Hard error `E_UNKNOWN_STUDENT` — import the STUDENT batch for this source first. | Confirmed. |
| UC-CR4 | **Result with a grade letter the source has no mapping for** (grade `P`) | Hard error `E_UNMAPPED_GRADE` — add the letter under Configure sources → Grade conversion. | Confirmed. |

> A legacy result **never** moves a live GPA on import. A registrar promotes one record at a time to
> transfer credit from the student's profile (Prior study → *Promote to transfer credit*); only that
> deliberate action flips `counts_toward_gpa`. The source's own attested GPA is shown beside the SPIMS
> GPA as a historical fact, never averaged in.

---

## 3. Finance opening balances

Files: `docs/legacy-import-use-cases/balance/`

| ID | Scenario | Expected outcome | Result |
|---|---|---|---|
| UC-B1 | **Owed and credit across two currencies, correct declared control totals** (7001 EGP owed, 7002 EGP credit, 7101 USD owed) | Each `owed > 0` → one Open `Invoice` (balance carried forward); each `credit > 0` → one wallet `Money` credit. Zero rows write nothing. | Confirmed — 2 invoices, 1 wallet credit. |
| UC-B2 | **Same file, wrong declared control totals** | Commit is **refused** to the minor unit — there is no override for a money mismatch. | Confirmed — commit refused with the money-gate message. |
| UC-B3 | **Unsupported currency** (`GBP`) | Hard error `E_UNSUPPORTED_CURRENCY` — the importer never invents an exchange rate. | Confirmed. |

> Committing a BALANCE batch requires **`finance.manage`** (Financial Admin) in addition to
> `import.commit`. Legacy invoices are excluded from automated dunning until a registrar opts a cohort in.

---

## 4. Credentials — old diplomas and certificates

Files: `docs/legacy-import-use-cases/credential/`

| ID | Scenario | Expected outcome | Result |
|---|---|---|---|
| UC-CD1 | **Legacy diploma with the source's own serial** (7001, PROGRAM_CERTIFICATE, `POPULI-DIP-2019-001`) | Imported as a historical `Credential` — the serial is kept **verbatim** and never touches SPIMS's own serial counter; `/verify` shows it as a historical record; it cannot be reissued. | Confirmed — verbatim serial, counter untouched. |
| UC-CD2 | **Credential type SPIMS doesn't recognise** (`DIPLOMA`) | Hard error `E_UNKNOWN_CREDENTIAL_TYPE` — the file must name one of TRANSCRIPT, PROGRAM_CERTIFICATE, STANDALONE_CERTIFICATE, OFFERING_COMPLETION. | Confirmed. |
| UC-CD3 | **Serial that collides with an existing (native) credential** (`SPIMS-CRED-2026-00001`) | Hard error `E_DUPLICATE_SERIAL` — a serial belonging to a different source (native included) is refused. | Confirmed. |

---

## 5. Mid-term cutover (live enrolments)

Files: `docs/legacy-import-use-cases/midterm/`

| ID | Scenario | Expected outcome | Result |
|---|---|---|---|
| UC-M1 | **Active student onto the live TH101 / Fall offering** (7101, Exam + Attendance) | Lands on the **live** offering (never a shadow); one `Enrollment` at `grade_status = IN_PROGRESS` and one `GradebookComponentScore` per component. | Confirmed — 1 enrolment, 2 scores, IN_PROGRESS. **See known issue #1.** |
| UC-M2 | **Course/semester that matches no live offering** (`ZZ999` / Fall) | Hard error `E_OFFERING_NOT_FOUND` — this import never creates an offering. | Confirmed. |
| UC-M3 | **Component name not on the offering** ("Midterm Exam") | Hard error `E_UNKNOWN_COMPONENT`. | Confirmed. |
| UC-M4 | **Re-importing a row for a student already enrolled** | Hard error `E_ENROLLMENT_ALREADY_EXISTS` (and `E_SCORE_ALREADY_EXISTS`) — never silently overwrites live grading work. | Confirmed — both errors raised, nothing overwritten. |

---

## Known issues & gaps found

These were surfaced by running the cases above against the current system. They are recorded here so
an administrator plans around them; none is a data-loss risk when the operational guidance is followed.

### Issue #1 — Mid-term import does not check whether the target gradebook is locked *(real gap)*

`MIDTERM_ENROLLMENT` is meant for offerings that are **live and in progress**. But the commit path
writes the `GradebookComponentScore` rows directly, without the lock check that the normal grade-entry
path (`GradebookService::setCellScore()`) enforces. In the seeded demo, TH101 / Fall is already
**submitted and locked**, yet UC-M1 still created an IN_PROGRESS enrolment and two scores on it.

- **Impact**: a mid-term file aimed at the wrong (already-finalised, locked) offering will silently
  write onto it instead of being refused. On a genuinely in-progress offering there is no problem.
- **Operational guidance**: only run a mid-term cutover file against offerings whose gradebooks are
  still open; verify the target offering is not locked before committing.
- **Suggested remediation**: refuse a row whose resolved offering has `gradebook_locked_at` set, with
  a new `E_OFFERING_GRADEBOOK_LOCKED` error, mirroring the "resolve exactly or refuse" posture the
  rest of this entity type already takes.

### Issue #2 — Offering resolution matches semester by name, which is not unique across years *(latent gap)*

A mid-term row identifies its offering by `course_code` + `semester_name`. Semester **names repeat
across academic years** — the seeded data alone has two semesters named "Fall" (2025/2026 and
2026/2027). Today only one carries a live TH101 offering, so UC-M1 resolves cleanly. But as soon as a
course has live offerings in two identically-named semesters, `semester_name = "Fall"` becomes
ambiguous (`E_OFFERING_AMBIGUOUS`) with no way to disambiguate other than the optional `offering_id`
column.

- **Operational guidance**: for a course that runs every year, supply the exact `offering_id` in the
  mid-term file rather than relying on the semester name.
- **Suggested remediation**: allow the file to qualify the semester by academic year (e.g.
  `Fall 2026/2027`), or surface the offering picker so a registrar chooses the exact section.

### Issue #3 — A downloaded legacy credential renders on the standard SPIMS template *(known, plan-acknowledged)*

`/verify` correctly distinguishes a historical (legacy) credential from a SPIMS-issued one. The
**downloaded PDF/HTML**, however, still renders through the normal certificate template, so a saved
file of a migrated diploma can look SPIMS-issued. This is already flagged in the plan (§23.2) as a
deliberate follow-up, not a defect in the verification path.

- **Suggested remediation**: add a "reproduction of a historical record" watermark to the download
  path for credentials whose `source_system` is set.

### Issue #4 — New people are queued on a surname match, not created *(behaviour, plan-conformant)*

Covered in full under UC-S1 above. Not a defect — but the single most likely operational surprise on a
real STUDENT import, so it is repeated in this list: **work the Identity review queue after every
STUDENT commit**, or surname-colliding new people never get imported.

---

## How to reproduce

1. On a demo/staging box: `php artisan migrate:fresh --seed` (never on production).
2. Sign in as `adm@spims.test` (password `Spims@Test2026!`).
3. Go to **Data import → New import** (`/admin/imports/create`).
4. Pick the source (POPULI), the entity type, and upload the matching file from
   `docs/legacy-import-use-cases/`. Commit students first, then the dependent types.
5. Compare what you see on the dry-run report against the **Expected outcome** column above.
