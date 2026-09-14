# Legacy import — use-case fixtures

Input files for the worked import scenarios documented in-portal at **System docs → Legacy import —
use cases & expected results** (`/system-docs/legacy-import-use-cases`, visible to signed-in
admins/Super Admins). Each file reproduces one or more rows from that catalogue.

Run against a freshly seeded demo/staging box only (`php artisan migrate:fresh --seed`), never
production. Sign in as `adm@spims.test` (`Spims@Test2026!`); commit a BALANCE batch as
`fin@spims.test`. Commit the STUDENT files first — every other entity type resolves its student by the
legacy id recorded at STUDENT commit.

| File | Entity | Cases | Expected headline |
|---|---|---|---|
| `student/student-alumni.csv` | STUDENT (Alumni) | UC-S1, UC-S2, UC-S5 | 7001 created ARCHIVED + program; 7002 no-email → synthetic address; 7003 unknown program → warning (and queued on surname match — see UC-S1 note) |
| `student/student-active.csv` | STUDENT (Active) | UC-S3 | 7101/7102 created PENDING, claim rows queued |
| `student/student-active-no-email.csv` | STUDENT (Active) | UC-S4 | `E_ACTIVE_NO_EMAIL`, blocked |
| `course-result/course-result.csv` | COURSE_RESULT | UC-CR1, UC-CR2 | shadow course/offering for OT-HIST-201; live TH101 reused; both GPA-isolated |
| `course-result/course-result-unknown-student.csv` | COURSE_RESULT | UC-CR3 | `E_UNKNOWN_STUDENT` |
| `course-result/course-result-unmapped-grade.csv` | COURSE_RESULT | UC-CR4 | `E_UNMAPPED_GRADE` |
| `balance/balance.csv` | BALANCE | UC-B1, UC-B2 | correct totals → 2 invoices + 1 wallet credit; wrong totals → commit refused |
| `balance/balance-bad-currency.csv` | BALANCE | UC-B3 | `E_UNSUPPORTED_CURRENCY` |
| `credential/credential.csv` | CREDENTIAL | UC-CD1 | historical credential, verbatim serial, counter untouched |
| `credential/credential-unknown-type.csv` | CREDENTIAL | UC-CD2 | `E_UNKNOWN_CREDENTIAL_TYPE` |
| `credential/credential-duplicate-serial.csv` | CREDENTIAL | UC-CD3 | `E_DUPLICATE_SERIAL` (collides with the seeded native `SPIMS-CRED-2026-00001`) |
| `midterm/midterm.csv` | MIDTERM_ENROLLMENT | UC-M1, UC-M4 | live TH101/Fall enrolment + 2 scores; re-run → `E_ENROLLMENT_ALREADY_EXISTS` |
| `midterm/midterm-unknown-offering.csv` | MIDTERM_ENROLLMENT | UC-M2 | `E_OFFERING_NOT_FOUND` |
| `midterm/midterm-unknown-component.csv` | MIDTERM_ENROLLMENT | UC-M3 | `E_UNKNOWN_COMPONENT` |

Mapping targets per entity type (for the mapping screen) are listed in the in-portal page and mirror
the demo walkthrough. Known issues surfaced by these cases (mid-term writing onto a locked gradebook;
semester-name ambiguity across years; legacy-credential download watermark; surname-collision
queuing) are documented in the same in-portal page under **Known issues & gaps found**.
