# Legacy student data import — plan

Bringing historical students from **two** predecessor systems into SPIMS: personal information,
programs enrolled, course enrolments and results, GPA, diplomas and certificates, and financial
position.

Status: **plan only.** No code written. Phases `L0`–`L6` below are the proposed build order.
Companion to [`docs/academic-roadmap/`](academic-roadmap/) (S0–S9, complete) — this is the first
phase whose subject is *data that already exists* rather than behaviour the app lacks.

Traces to two open items in [`docs/system-code-review.md`](system-code-review.md) Band A:
*Transfer credit (manual `academic_records`)* and *Bulk CSV import*.

---

## 1. Decisions already taken

Four architectural choices are settled and everything below assumes them.

| # | Decision | Chosen | Consequence |
|---|---|---|---|
| D1 | **Import depth** | **Transcript-level only** | Per student: identity, programs, course enrolments with final grade / letter / credits / term, GPA, credentials, financial position. **No** per-assignment scores, no attendance rows, no submissions, no discussion posts, no week progress. |
| D2 | **Catalog mapping** | **Shadow legacy catalog** | Legacy courses / programs / offerings are created as real rows marked `source_system`, hidden from catalog, registration, roster, gradebook and reports. Exact code matches link to the live catalog instead. |
| D3 | **GPA** | **Preserve legacy GPA verbatim; recompute SPIMS GPA separately** | `student_programs.cached_gpa` keeps meaning "computed by SPIMS from SPIMS grade bands". Legacy GPA is stored as an attested historical figure with its own scale and source, and rendered beside it. |
| D4 | **Finance** | **Opening balance per student per currency** | One synthetic invoice (owed) or wallet credit (in hand) dated at cutover. No decade of transactions, no historical FX, no receipt-serial archaeology. |

What D1–D4 buy: the import touches **eight** tables of consequence rather than forty, every number
it writes is one a registrar can check against a control total, and nothing it writes can silently
change a live student's standing.

---

## 2. What "two different systems" forces

Two sources is not two runs of one importer. It changes the design in four places:

1. **Grade scales differ.** Source A may be 4.00-point letters, source B percent-only or 5.00-point.
   Conversion must be *per source*, table-driven, and visible — never hardcoded.
2. **Natural keys collide.** Student `1042` exists in both. Every legacy reference is
   `(source, entity_type, legacy_id)`, never `legacy_id` alone.
3. **The same human appears in both.** Cross-source identity resolution is a first-class feature
   with a human review queue, not a `WHERE email =` clause.
4. **Control totals are per source.** Reconciliation is per source per currency per entity; a
   combined total hides a source that silently imported half its rows.

---

## 3. Data model

All migrations additive (hard rule 5). New tables, plus nullable columns on existing ones.

### 3.1 The import spine (new tables)

```
import_sources
  id ulid pk
  code            string unique          -- 'LEGACY_A', 'LEGACY_B'
  name            string
  gpa_scale_max   decimal(5,2)           -- 4.00 | 5.00 | 100.00
  default_currency string                -- Currency enum value
  timezone        string
  notes           text nullable
  active          bool default true
  timestamps

import_grade_mappings                    -- per-source grade conversion, table-driven
  id ulid pk
  source_id       -> import_sources cascade
  legacy_letter   string nullable        -- 'A-', 'ممتاز', null when percent-banded
  min_percent     float nullable
  max_percent     float nullable
  spims_letter    string                 -- letter shown on the SPIMS transcript
  gpa_points      float                  -- SPIMS points, informational until promoted
  is_passing      bool
  counts_toward_gpa bool default false   -- W / I / AU / P map to false
  unique (source_id, legacy_letter)

import_batches
  id ulid pk
  source_id       -> import_sources
  entity_type     string                 -- STUDENT | PROGRAM | RESULT | CREDENTIAL | FINANCE
  status          string                 -- DRAFT|STAGED|VALIDATED|DRY_RUN|COMMITTED|ROLLED_BACK|FAILED
  file_name       string
  file_hash       string                 -- sha256; identical re-upload refused unless --force
  row_count / error_count / warning_count  unsigned int
  control_totals  json nullable          -- registrar's declared totals, checked before commit
  dry_run_report  json nullable
  sealed_at       timestamp nullable     -- after this, rollback refused
  created_by_id / committed_by_id / rolled_back_by_id -> users nullable
  created_at, staged_at, validated_at, committed_at, rolled_back_at
  index (source_id, entity_type, status)

import_rows                              -- staging; immutable after commit
  id ulid pk
  batch_id        -> import_batches cascade
  row_number      unsigned int
  natural_key     string                 -- the source system's own id
  payload         json                   -- raw, exactly as parsed
  normalized      json nullable          -- after transform
  before_snapshot json nullable          -- for rollback of UPDATE actions
  action          string nullable        -- CREATE | UPDATE | LINK | NOOP
  status          string                 -- PENDING|VALID|WARN|ERROR|SKIPPED|APPLIED|ROLLED_BACK
  messages        json                   -- [{level, code, field, message_key, params}]
  target_type / target_id  nullable
  unique (batch_id, natural_key)
  index (batch_id, status)

import_links                             -- the permanent idempotency map; outlives batches
  id ulid pk
  source_id       -> import_sources
  entity_type     string                 -- 'user'|'student_program'|'enrollment'|'academic_record'|...
  legacy_id       string
  target_type / target_id
  first_batch_id / last_batch_id -> import_batches nullable
  timestamps
  unique (source_id, entity_type, legacy_id)
  unique (source_id, target_type, target_id)
  index (target_type, target_id)

import_merge_candidates                  -- cross-source / cross-record identity review queue
  id ulid pk
  source_id, legacy_id, batch_id
  candidate_user_id -> users
  score           float
  matched_on      json                   -- ['email','dob','name']
  status          string                 -- PENDING | MERGED | REJECTED | NEW_USER
  resolved_by_id -> users nullable
  resolved_at     timestamp nullable
  timestamps

legacy_academic_summaries                -- D3: the attested legacy GPA
  id ulid pk
  student_id      -> users
  source_id       -> import_sources
  program_id      -> programs nullable
  student_program_id -> student_programs nullable
  scope           string                 -- PROGRAM | CUMULATIVE
  gpa             decimal(6,3) nullable
  gpa_scale       decimal(5,2)           -- as reported by the source, never converted
  credits_attempted / credits_earned  unsigned int
  standing        string nullable        -- the source's own words
  honors          string nullable
  as_of           date
  attested_by_id -> users nullable       -- registrar sign-off
  attested_at     timestamp nullable
  unique (student_id, source_id, scope, program_id)
```

### 3.2 Additive columns on existing tables

One concept, repeated: `source_system` nullable string, **null means native SPIMS**. Legacy ids
live in `import_links`, not in these columns.

| Table | Added | Why behaviour depends on it |
|---|---|---|
| `users` | `source_system` | Profile shows provenance; alumni excluded from active-student counts |
| `programs` | `source_system` | Shadow programs hidden from the catalog and from admissions forms |
| `courses` | `source_system` | Shadow courses hidden from catalog, prerequisites, interest flags |
| `course_offerings` | `source_system` | Shadow offerings excluded from registration, roster, gradebook, live, attendance, completion, reports |
| `student_programs` | `source_system` | Degree audit renders a prior-study section |
| `enrollments` | `source_system` | Excluded from credit caps, add/drop, gradebook submit/lock, completion evaluation |
| `academic_records` | `source_system`, **`counts_toward_gpa` bool default true** | The one behaviour change in existing code — see §5 |
| `credentials` | `source_system`, `issuing_body`, `original_issued_at` | `/verify` renders a distinct historical state; `regenerate()` refused |
| `invoices` | `source_system` | Excluded from dunning and gateway payment |
| `payments` | `source_system` | Excluded from receipt generation and refund flow |

Index `source_system` on `course_offerings`, `enrollments`, `academic_records`, `invoices` — those
are the four filtered in hot paths.

### 3.3 Enum additions (string columns, so code-additive, no migration)

- `UserStatus::Archived = 'ARCHIVED'` — an imported alumnus with no portal access.
- `LedgerReason::LegacyCarryForward = 'LEGACY_CARRY_FORWARD'` — opening wallet credit.
- `AttendanceSource` / `GradeType` unchanged; D1 imports no attendance and `GradeType::Standard`
  plus `Withdrawal` and `PassFail` already cover what legacy result rows carry.

---

## 4. Identity resolution

`users.email` is `unique` and **not nullable**, and rule 5 forbids altering it. So:

- A legacy student with a real email gets it.
- One without gets a reserved synthetic address:
  `legacy.<source_code>.<legacy_id>@no-email.invalid`, `email_verified = false`,
  `password_hash = null`, `status = ARCHIVED`.
- The mail layer gains one guard: refuse any send to `@no-email.invalid`, with a test.
  (Rule 7 already tolerates a silent mailer; this makes the silence deliberate.)

**Matching ladder**, applied in order, per incoming row:

| Rung | Rule | Outcome |
|---|---|---|
| 1 | `import_links` hit on `(source, 'user', legacy_id)` | Same person. Update in place. |
| 2 | Exact normalised email match against an existing user | Auto-link. |
| 3 | Exact match on national id / legacy student number, when the source supplies one and the field is populated on both sides | Auto-link. |
| 4 | Normalised `first_name + last_name + date_of_birth`, where the DOB is present **and** the triple is unique school-wide | Auto-link. |
| 5 | Name similarity, or name + phone, or name without DOB | **Never** auto-linked. Queued in `import_merge_candidates`. |

Rules: two *existing native* SPIMS users are never merged by the importer (out of scope, §11 Q7).
A single legacy person appearing in both sources resolves to one `users` row with two
`import_links` rows. Name normalisation must handle Arabic: strip tashkeel, normalise
alef/hamza forms and ta-marbuta, collapse whitespace — and must **not** transliterate.

---

## 5. Academic records, GPA, and transfer credit

### What gets written per legacy course result

1. A shadow `course_offering` for `(legacy course, legacy term)` — created once, reused.
   `status = Archived`, `source_system` set, no weeks, no content, no gradebook components.
2. An `enrollment` on it — `status = Completed` / `Withdrawn`, `grade_status = Locked`,
   `final_percent` / `final_letter` / `final_gpa_points` from the per-source mapping,
   `progress_percent = 100`, `source_system` set.
3. An `academic_record` — `enrollment_id` pointing at (2), `term` = the legacy term string,
   `credit_hours` from the source (**not** from the SPIMS course, which may differ),
   `counts_toward_gpa` from `import_grade_mappings`, defaulted to **false** for all legacy rows
   in v1 regardless of the mapping (see §11 Q3).

### The one change to existing code

`GradebookService::refreshGpa()` currently sums every fulfilment's record. It gains a single
filter: `->where('counts_toward_gpa', true)`. Native records default to `true`, so behaviour for
every existing student is unchanged — provable by running the existing suite untouched.

That one filter delivers three things at once:

- Legacy results cannot move a live GPA (D3).
- **Transfer credit** becomes expressible: a registrar may attach a legacy record to a
  `program_requirement_fulfillment` — credit granted, grade excluded — which is the standard
  registrar rule and closes the Band A gap.
- A registrar can later *promote* a specific record to count toward GPA, audited, one record at a
  time, through a service method rather than a data fix.

### What the student sees

`TranscriptController` and the degree audit gain a **Prior study** section: legacy records grouped
by source and term, each labelled with the source name, the original letter, and the original
scale; and the `legacy_academic_summaries` figure rendered as e.g.
*"GPA 3.42 / 4.00 as recorded by <source>, as of 2019-06-30"* — never merged into the SPIMS number,
never averaged with it.

---

## 6. Finance (D4)

Per student, per currency, at a single declared cutover date:

- **Owed > 0** → one `Invoice`, `source_system` set, `status = Open`, one `InvoiceLine`
  *"Balance carried forward from <source> as of <date>"*, `offering_id = null`,
  `total_minor` as an integer (rule 3 — the importer parses money as a decimal string and
  multiplies by 100 with banker-safe integer arithmetic; a value with more than two decimal places
  is an **error**, never a rounding).
- **Credit > 0** → one `WalletTransaction`, `kind = Money`, `direction = Credit`,
  `reason = LegacyCarryForward`, against the student's `WalletAccount` (created if absent).
- **Zero** → nothing. No empty invoices.

Hard gate: the batch declares `control_totals` — rows, distinct students, and total minor units per
currency. **Commit is refused unless the computed sum matches exactly.** This is the single most
valuable control in the whole feature and it is cheap, because money is already integer minor units.

Legacy invoices are excluded from `DunnOverdueInstallmentsCommand` and from the gateway until a
registrar opts them in (§11 Q9).

---

## 7. Diplomas and credentials

Legacy diplomas and certificates import as `Credential` rows:

- `type` reuses `ProgramCertificate` / `StandaloneCertificate`; no new enum case.
- `serial` namespaced `LEG-<SOURCE>-<n>` so it cannot collide with `CredentialService::nextSerial()`.
- `qr_token` minted normally, so the public `/verify` URL works.
- `issuing_body` and `original_issued_at` record who actually awarded it and when.
- `file_url` points at the scanned original **if** the school supplies scans; otherwise null.

`/verify` renders a third state beside valid and revoked: *"Historical record. Migrated from
<source> on <date>. Originally issued by <issuing body> on <date>."* SPIMS must not appear to have
minted a document it did not mint.

`CredentialService::regenerate()` refuses any credential with `source_system` set. A registrar who
wants a SPIMS-branded reissue mints a **new native** credential, linked back to the legacy one.

---

## 8. Pipeline and safety

```
 upload  →  STAGE  →  VALIDATE  →  DRY RUN  →  COMMIT  →  RECONCILE   ( →  ROLLBACK )
            parse      rules,      full diff,   in one     control       until sealed
            to rows    mappings,   zero writes  txn per    totals,
                       matching                 chunk      per source
```

**Staging** parses the file into `import_rows` with the payload untouched. Nothing else is written.

**Validation** applies per-source grade mappings, catalog resolution and the matching ladder,
producing `messages` with stable codes (`E_UNMAPPED_GRADE`, `E_MONEY_PRECISION`,
`E_DUPLICATE_NATURAL_KEY`, `W_AMBIGUOUS_IDENTITY`, `W_UNKNOWN_TERM`, …). Errors block the row;
warnings do not. The full message list downloads as CSV in the registrar's locale.

**Dry run** executes the whole commit path inside a transaction that is always rolled back, and
reports the diff: *N users created, M linked, P programs, Q results, totals per currency*.
A dry run that reports zero writes for a non-empty file is itself an error.

**Commit** is chunked (1 000 rows) and queued above 5 000 rows, each chunk wrapped by
`AuditLogWriter::withAudit()` (rule 2) with the acting admin as actor — not a system user, because
accountability for a mass write matters more than tidiness. Per-row audit rows would be millions;
`import_rows` is the per-row record and is immutable after commit, and `PruneAuditLogsCommand`
must not touch the import tables.

**Side effects are suppressed.** An `ImportContext::runSilently()` guard, checked by
`NotificationService`, the communications dispatcher, the receipt/PDF renderer and the credential
issuer, so a commit sends zero mail, writes zero `communication_logs`, renders zero PDFs and
charges nothing. Completion-criteria evaluation and `AcademicStandingService::apply()` are deferred
to a single post-batch pass. This is tested by assertion, not by inspection.

**Rollback** is available until `sealed_at` (default 30 days, a `Setting`). It reverses `CREATE`
actions and restores `before_snapshot` for `UPDATE`s, and is **refused** when any imported row is
now referenced by native data — a legacy record attached to a fulfilment, a legacy invoice with a
native payment against it. The refusal names the blocking rows.

---

## 9. Surfaces

**Permissions** — six new keys in `config/permissions.php`, none offering-scoped (they are
school-wide by nature), so all six are deliberately absent from `permission_scopes.php`
`offering_scoped`; `docs/role-matrix.md` gains a row each.

| Key | SUPER | ADMINISTRATIVE | ACADEMIC | FINANCIAL | others |
|---|---|---|---|---|---|
| `import.view` | bypass | R | R | R | — |
| `import.configure` | bypass | F | — | — | — |
| `import.stage` | bypass | F | F | — | — |
| `import.commit` | bypass | F | — | — | — |
| `import.rollback` | bypass | F | — | — | — |
| `import.merge_resolve` | bypass | F | — | — | — |

Finance batches additionally require `finance.manage` on the acting user, so a registrar cannot
commit money.

**Routes** — appended under a new `// --- TRACK: legacy-import ---` anchor at the end of
`routes/web.php`. The file is never reordered.

**Admin screens** (`docs/design-system.md` first, per the global contract):

| Route | What |
|---|---|
| `/admin/imports` | Batches per source, status, counts, who committed |
| `/admin/imports/sources` | The two systems: scale, currency, timezone, grade mapping grid |
| `/admin/imports/create` | Upload → column mapping → declared control totals |
| `/admin/imports/{batch}` | Validation report, dry-run diff, Commit behind a typed confirmation |
| `/admin/imports/{batch}/rows` | Row inspector filtered by status; fix-and-restage |
| `/admin/imports/merges` | Merge candidate queue: merge / reject / create new |

**CLI**, for volume and for the rehearsal runs:
`import:stage`, `import:validate`, `import:dry-run`, `import:commit --confirm`, `import:rollback`,
`import:reconcile`.

**Localisation** — every string in `lang/{ar,en,fr}/import.php`, a new per-step file rather than an
append to a shared one. Validation message codes are keys, not sentences. `LocaleParityTest` gates.

---

## 10. Phases

| Phase | Scope | Done when |
|---|---|---|
| **L0** | Intake contract: the exact column spec per entity per source, agreed with whoever exports the old systems. `import_sources` + `import_grade_mappings` + admin screen. No ingest. | Both schools' exports validate against a published spec; both grade scales are entered and a conversion table renders |
| **L1** | Batch spine: stage → validate → dry-run → commit → rollback, generic, proven on `STUDENT` only | A student file imports twice and creates rows once; dry run writes nothing; rollback restores exactly; zero mails sent |
| **L2** | Identity: matching ladder, Arabic normalisation, merge queue | A person present in both sources lands as one user with two links; an ambiguous pair reaches the queue and never auto-merges |
| **L3** | Shadow catalog, program enrolments, course results, `legacy_academic_summaries`, the `counts_toward_gpa` filter | A live student's `cached_gpa` is byte-identical before and after importing legacy results for them; shadow offerings appear in no catalog, roster, gradebook or report |
| **L4** | Transcript / degree-audit / profile surfacing; registrar promotion of a record to transfer credit | A student sees prior study and both GPAs, correctly labelled, in all three locales; a promoted record moves GPA and is audited |
| **L5** | Finance opening balances + reconciliation gate | Control totals mismatch by one piastre and the commit is refused; matched totals commit and the AR report balances per source per currency |
| **L6** | Legacy credentials + `/verify` historical state + `regenerate()` refusal | A migrated diploma verifies publicly as historical and cannot be reissued under a SPIMS serial |

Suggested cut line for a first release: **L0–L3**. That is a complete, checkable academic history
with no risk to live GPA. L5 and L6 carry the outward-facing risk (money and documents) and benefit
from a rehearsal against real exports first.

---

## 11. Open questions

Recommended default in bold. Answer any of these and the affected phase is unblocked; the rest can
proceed on the defaults.

**Sources and intake**

1. What are the two systems, and how will data leave them — CSV/XLSX export, a database dump, or an
   API? **Default: CSV/XLSX per entity, one file per entity per source**, because it is the only
   form a registrar can proofread before commit.
2. Roughly how many students, results and diplomas per source? Anything above ~50 000 result rows
   changes L1 from synchronous to queued-by-default.
3. Is this a **one-shot cutover**, or a parallel-run period with repeated delta re-imports?
   **Default: one-shot, with re-run idempotency built anyway** (`import_links` gives it for free).
   A parallel run adds a reconciliation report per cycle — that is L7.

**Academic**

4. Should a legacy record be allowed to **satisfy a program requirement** (credit granted, grade
   excluded from GPA)? **Default: yes, opt-in per record, registrar-approved, audited.** Without
   it, an alumnus re-entering a program appears to have completed nothing.
5. Legacy terms rarely map to SPIMS `semesters`. **Default: store the legacy term as the free-text
   `academic_records.term` and create no `semesters` rows.** Alternative: synthesise historical
   semesters so term-based reporting spans both eras — more faithful, more rows, and they would
   need `status = Archived` handling everywhere.
6. Credit hours conflict: legacy says 3, the matched SPIMS course says 4. **Default: the record
   keeps the legacy credits; the course keeps its own.** Flag as a warning, never silently
   normalise.
7. Are there **W / I / AU / P** style non-grades, and do withdrawals need to appear on the
   transcript at all? **Default: import them with `counts_toward_gpa = false` and show them**,
   because a transcript that hides withdrawals is not a transcript.
8. Two *native* SPIMS users turn out to be the same person. **Default: out of scope for v1** — the
   importer refuses and queues it for manual handling. A real user-merge tool is its own feature.

**Financial**

9. Should imported opening balances be **collectable** — chased by dunning, payable through the
   gateway, visible on the student's balance? **Default: visible and payable, but excluded from
   automated dunning** until a registrar opts a cohort in.
10. What is the cutover date, and is it the same for both systems? A different date per source is
    fine but must be stated per source and shown on the invoice line.
11. Currencies beyond EGP and USD in the old data? The `Currency` enum has exactly two. Anything
    else needs either a new enum case or a stated conversion policy **at a rate the school
    declares** — the importer will not invent an FX rate.
12. Do old **receipt numbers** need to survive for audit? Under D4 they do not come across at all.
    If auditors need them, the minimal addition is a note field on the carried-forward invoice line
    rather than a full payment history.

**Identity, access and privacy**

13. Do imported alumni get **portal logins**? **Default: no — `status = ARCHIVED`, no password**,
    with an explicit per-student or per-batch "invite to claim account" action as a later step.
    Turning it on for thousands of stale email addresses is a deliverability and support event, not
    a checkbox.
14. Which personal fields actually come across? **Default: name, DOB, phone, country, preferred
    locale, legacy student number.** National ID, addresses, family data and photographs are
    excluded unless someone names a use for them — SPIMS has no field for most of them today, and
    adding them is a bigger privacy decision than an import.
15. Are any of the old students **minors**, and does that change retention or who may view the
    record? Affects nothing structurally, but it does affect whether L4 exposes these records in
    the student portal at all.
16. **Never** imported: password hashes, session tokens, security answers. Stated here so nobody
    proposes it. Confirm you agree.

**Operations**

17. Who is authorised to press **Commit**? **Default: Administrative Admin only**, with Financial
    Admin additionally required for finance batches. Super Admin bypasses by design.
18. Rollback window before a batch seals. **Default: 30 days**, configurable.
19. Where do the source export files live after import? They contain the school's full student
    body. **Default: private storage, hashed and referenced from the batch, purged on seal** — not
    in the repo, not in `public/`.
20. Should the two sources be imported **sequentially** (A fully, then B, so B's rows resolve
    against A's already-created users) or interleaved? **Default: sequential, larger source first.**
    It makes the merge queue far smaller and the reconciliation legible.

---

## 12. Risks

| Risk | Mitigation |
|---|---|
| A legacy import silently changes a live student's GPA or standing | `counts_toward_gpa` defaults false for all legacy rows; `ImportGpaIsolationTest` asserts `cached_gpa` is unchanged before/after |
| Thousands of emails fire on commit | `ImportContext::runSilently()` plus `ImportSilenceTest` asserting zero `communication_logs` and zero mails for a full run |
| Shadow catalog leaks into the public catalog, registration or reports | `source_system IS NULL` filters, and `ImportCatalogIsolationTest` sweeping every catalog, roster, gradebook and report query |
| Money drifts by rounding | Integer minor units end to end (rule 3); more than two decimal places is a hard error; control totals gate the commit |
| A re-run duplicates the whole school | `import_links` unique on `(source, entity_type, legacy_id)`; `file_hash` refuses an identical re-upload; `ImportIdempotencyTest` |
| Two people merged into one, or one person split into two | Rungs 1–4 only on deterministic keys; everything else queues for a human; merges are audited and reversible while the batch is unsealed |
| A migrated diploma appears to be SPIMS-issued | Namespaced serials, `issuing_body`, a distinct `/verify` state, and `regenerate()` refused on legacy credentials |

---

## 13. Test plan

New suite under `tests/Feature/Import/`, and the step gate
(`./scripts/validate-step.sh`) wired in `prompts/steps.tsv` per phase.

`ImportIdempotencyTest` · `ImportDryRunTest` · `ImportRollbackTest` · `ImportGpaIsolationTest` ·
`ImportTransferCreditTest` · `ImportSilenceTest` · `ImportFinanceReconciliationTest` ·
`ImportCatalogIsolationTest` · `ImportIdentityMatchTest` (incl. Arabic normalisation) ·
`ImportMergeQueueTest` · `ImportPermissionTest` (every role × every new key, deny and allow) ·
`ImportCredentialVerifyTest` · `LocaleParityTest` (existing, must stay green).

The existing suite must pass **unmodified** after L3 — that is the proof that the
`counts_toward_gpa` filter changed nothing for native students.
