# Legacy data import — Populi + Canvas → SPIMS

Migrating the school's existing student body and its history out of **Populi** (SIS) and
**Canvas** (LMS) into SPIMS: identity, programs, course results, GPA, diplomas and financial
position — plus working portal accounts for the students who are still studying.

Status: **L0/L1 foundation shipped** — source + grade-mapping config, the mapping engine, and the
full upload → map → validate → dry-run → commit → rollback pipeline for the `STUDENT` entity,
including the "exact catalog match" half of D2 and the `ARCHIVED`/`PENDING` split from D8. Phases
`L0`–`L8` below are the full build order; §17 at the end of this document is the precise as-shipped
boundary — what landed, what was deliberately simplified, and what is still open.

Companion to [`docs/academic-roadmap/`](academic-roadmap/) (S0–S9, complete). This is the first
phase whose subject is *data that already exists* rather than behaviour the app lacks, and the
first that has a hard external deadline attached to it — the day the school stops paying for
Populi.

Closes two Band A items in [`docs/system-code-review.md`](system-code-review.md): *Transfer credit
(manual `academic_records`)* and *Bulk CSV import*.

---

## 1. The two sources are not peers

| | **Populi** | **Canvas** |
|---|---|---|
| What it is | Student Information System | Learning Management System |
| Authoritative for | Identity, programs/degrees, official final grades, credit hours, GPA, conferrals, **all money** | Nothing official |
| Useful for | Everything above | The `sis_user_id` ↔ Populi ID crosswalk; courses that exist only in Canvas; final grades Populi never received |
| Export format | CSV / XLSX report downloads | CSV provisioning report + per-course gradebook export |
| Grade shape | Letter + grade points, school-configured scale | Percent + letter from a grading standard |

**Precedence rule, applied everywhere:** where Populi and Canvas disagree about a person, a course,
a term or a grade, **Populi wins**. Canvas may only fill a field Populi left empty, and every such
fill is recorded as a `W_FILLED_FROM_CANVAS` warning on the row so a registrar can see it.

**The join key is free.** In any competently configured Populi↔Canvas integration, the Canvas
`SIS User ID` column *is* the Populi person ID. That turns cross-source identity from fuzzy
matching into a lookup, and it is the single most valuable column in the whole migration. §6 makes
it rung 1 of the ladder.

**Canvas may reduce to one file.** Under the transcript-level depth decided in §2, Canvas's only
essential contribution is that crosswalk. If every official grade already lives in Populi — which
is the normal arrangement, because Populi is where transcripts are printed from — the Canvas import
collapses from five entity types to a single two-column identity file. This is open question Q1 and
it is worth answering early, because a *yes* removes roughly a third of the build.

---

## 2. Decisions taken

| # | Decision | Chosen | Consequence |
|---|---|---|---|
| D1 | **Import depth** | Transcript-level | Identity, programs, course enrolments with final grade / letter / credits / term, GPA, credentials, financial position. **No** per-assignment scores, attendance, submissions, discussion posts or week progress. |
| D2 | **Catalog mapping** | Shadow legacy catalog | Legacy courses / programs / offerings exist as real rows marked `source_system`, hidden from catalog, registration, roster, gradebook and reports. Exact code matches link to the live catalog instead. |
| D3 | **GPA** | Preserve legacy GPA; recompute SPIMS GPA separately | `student_programs.cached_gpa` keeps meaning "computed by SPIMS from SPIMS grade bands". Populi's GPA is stored as an attested figure with its own scale, rendered beside it, never merged into it. |
| D4 | **Finance** | Opening balance per student per currency | One synthetic invoice (owed) or wallet credit (in hand) dated at cutover. No transaction history, no historical FX, no receipt archaeology. |
| D5 | **Intake format** | CSV / XLSX | Report exports from both systems. No API integration, no database dump. |
| D6 | **Cutover** | One shot | Idempotent re-runs are still built — the fix-and-re-import cycle is guaranteed — but there is no parallel-run period and no delta sync. |
| D7 | **Transfer credit** | Yes, opt-in per record | A legacy record may satisfy a program requirement (credit granted) while excluded from GPA (grade not counted). Registrar-approved, audited, one record at a time. |
| D8 | **Portal accounts** | Alumni no, active students yes | Alumni import as `ARCHIVED` with no login. Currently-enrolled students import as `PENDING` and claim their account through the existing OTP → set-password flow. |
| D9 | **Credentials** | Never imported | No password hashes, no session tokens, no security answers, no API keys. Every credential in SPIMS is newly minted. |

---

## 3. Two populations, two jobs

This is the structural consequence of D8, and it is easy to miss: **this is not one migration, it
is two that share a pipeline.**

| | **Alumni / historical** | **Active students** |
|---|---|---|
| Who | Graduated, withdrawn, or long dormant | Currently enrolled and studying |
| `users.status` | `ARCHIVED` | `PENDING` → `ACTIVE` on claim |
| Password | None, ever | Set by the student (existing `auth.password.create` flow) |
| Email | Real, or synthetic `@no-email.invalid` | **Must be real.** A missing or synthetic email is a hard `E_ACTIVE_NO_EMAIL` error, not a warning |
| `student_programs.status` | `COMPLETED` / `WITHDRAWN` | `ACTIVE` |
| Past coursework | Shadow legacy records | Shadow legacy records |
| Portal | None | Full |
| Failure mode if wrong | A historical record is slightly off | **A real student cannot attend school on Monday** |

The second column is a live cutover with people on the other end of it, and it deserves its own
rehearsal, its own reconciliation, and its own support plan. Everything in this document that reads
"the registrar checks a total" applies doubly to the active cohort, where the total is a headcount
that must match the term roster exactly.

### Cutover timing — the one decision left that changes scope

- **V1 — term-boundary cutover (recommended, and what this plan builds).** SPIMS goes live between
  terms. Nothing is in flight. Active students arrive with completed history plus an `ACTIVE`
  `student_program`, claim their account, and register for the first SPIMS term through the normal
  flow. D1 holds without exception.
- **V2 — mid-term cutover (conditional phase L7).** Students are halfway through courses. Their
  current-term enrolments must land on **live** SPIMS offerings, not shadow ones, with
  `grade_status = IN_PROGRESS` and partial gradebook state imported from the Canvas per-course
  gradebook export. This breaches D1 for one term, needs the registrar to have built the live
  offerings and gradebook components *first*, and roughly doubles L3.

Plan for V1. Build L7 only if the calendar forces it. See Q3.

---

## 4. Data model

All migrations additive (hard rule 5). New tables, plus nullable columns on existing ones.

### 4.1 Import spine

```
import_sources
  id ulid pk
  code             string unique        -- 'POPULI' | 'CANVAS'
  name             string
  kind             string               -- SIS | LMS   (drives the precedence rule)
  precedence       unsigned int         -- lower wins on conflict; Populi 1, Canvas 2
  gpa_scale_max    decimal(5,2)         -- 4.00
  default_currency string
  timezone         string
  active           bool default true
  timestamps

import_grade_mappings                   -- per-source grade conversion, table-driven, never hardcoded
  id ulid pk
  source_id        -> import_sources cascade
  legacy_letter    string nullable      -- 'A-', 'P', 'W', 'I', 'AU'
  min_percent      float nullable       -- for percent-only sources (Canvas)
  max_percent      float nullable
  spims_letter     string
  gpa_points       float
  is_passing       bool
  counts_toward_gpa bool default false  -- W / I / AU / P → false
  unique (source_id, legacy_letter)

import_mapping_profiles                 -- §5: a saved column→field mapping, reusable
  id ulid pk
  source_id        -> import_sources cascade
  entity_type      string
  name             string
  mappings         json                 -- [{column, target_field, transform, options, confidence, origin}]
  constants        json                 -- fixed values applied to every row
  ignored_columns  json                 -- explicitly acknowledged, not silently dropped
  is_default       bool default false
  proposed_by      string nullable      -- 'HEURISTIC' | 'AI' | 'MANUAL'
  created_by_id -> users
  timestamps
  unique (source_id, entity_type, name)

import_batches
  id ulid pk
  source_id        -> import_sources
  entity_type      string               -- STUDENT | PROGRAM | RESULT | CREDENTIAL | FINANCE | IDENTITY_XWALK
  population       string nullable      -- ALUMNI | ACTIVE   (§3; drives status + email strictness)
  status           string               -- DRAFT|MAPPED|VALIDATED|DRY_RUN|COMMITTING|COMMITTED|ROLLED_BACK|FAILED
  mapping_profile_id -> import_mapping_profiles nullable
  file_name        string
  file_path        string               -- private disk
  file_hash        string               -- sha256; identical re-upload refused unless forced
  sheet_name       string nullable      -- XLSX worksheet
  header_row       unsigned int default 1
  row_count / error_count / warning_count  unsigned int default 0
  control_totals   json nullable        -- registrar's declared totals; commit gate
  dry_run_report   json nullable
  commit_progress  json nullable        -- {processed, total} for the polling progress screen
  sealed_at        timestamp nullable   -- after this, rollback refused
  created_by_id / committed_by_id / rolled_back_by_id -> users nullable
  created_at, mapped_at, validated_at, dry_run_at, committed_at, rolled_back_at
  index (source_id, entity_type, status)

import_rows                             -- staging; immutable after commit
  id ulid pk
  batch_id         -> import_batches cascade
  row_number       unsigned int
  natural_key      string               -- the source system's own id
  payload          json                 -- raw, exactly as parsed
  normalized       json nullable        -- after mapping + transforms
  before_snapshot  json nullable        -- rollback of UPDATE actions
  action           string nullable      -- CREATE | UPDATE | LINK | NOOP
  status           string               -- PENDING|VALID|WARN|ERROR|SKIPPED|APPLIED|ROLLED_BACK
  messages         json                 -- [{level, code, field, message_key, params}]
  target_type / target_id nullable
  unique (batch_id, natural_key)
  index (batch_id, status)

import_links                            -- the permanent idempotency map; outlives batches
  id ulid pk
  source_id        -> import_sources
  entity_type      string               -- 'user'|'student_program'|'enrollment'|'academic_record'|'credential'|'invoice'
  legacy_id        string
  target_type / target_id
  first_batch_id / last_batch_id -> import_batches nullable
  timestamps
  unique (source_id, entity_type, legacy_id)
  unique (source_id, target_type, target_id)
  index (target_type, target_id)

import_merge_candidates                 -- identity review queue
  id ulid pk
  source_id, legacy_id, batch_id
  candidate_user_id -> users
  score            float
  matched_on       json                 -- ['sis_id','email','dob','name']
  payload_preview  json                 -- what the sheet says, for side-by-side compare
  status           string               -- PENDING | MERGED | REJECTED | NEW_USER
  resolved_by_id -> users nullable
  resolved_at      timestamp nullable
  timestamps

legacy_academic_summaries               -- D3: the attested Populi GPA
  id ulid pk
  student_id       -> users
  source_id        -> import_sources
  program_id       -> programs nullable
  student_program_id -> student_programs nullable
  scope            string               -- PROGRAM | CUMULATIVE
  gpa              decimal(6,3) nullable
  gpa_scale        decimal(5,2)         -- as reported, never converted
  credits_attempted / credits_earned unsigned int
  standing         string nullable      -- the source's own words
  honors           string nullable
  as_of            date
  attested_by_id -> users nullable
  attested_at      timestamp nullable
  unique (student_id, source_id, scope, program_id)

import_account_claims                   -- §9: the active-student activation cohort
  id ulid pk
  user_id          -> users
  batch_id         -> import_batches nullable
  status           string               -- QUEUED | SENT | CLAIMED | BOUNCED | EXPIRED | CANCELLED
  invited_at / claimed_at / last_reminded_at timestamp nullable
  reminder_count   unsigned int default 0
  timestamps
  unique (user_id)
```

### 4.2 Additive columns on existing tables

One concept repeated: `source_system` nullable string, **null means native SPIMS**. Legacy ids live
in `import_links`, not in these columns.

| Table | Added | Behaviour that depends on it |
|---|---|---|
| `users` | `source_system` | Provenance on the profile; alumni excluded from active-student counts |
| `programs` | `source_system` | Shadow programs hidden from catalog and admissions forms |
| `courses` | `source_system` | Shadow courses hidden from catalog, prerequisites, interest flags |
| `course_offerings` | `source_system` | Shadow offerings excluded from registration, roster, gradebook, live, attendance, completion, reports |
| `student_programs` | `source_system` | Degree audit renders a prior-study section |
| `enrollments` | `source_system` | Excluded from credit caps, add/drop, gradebook submit/lock, completion evaluation |
| `academic_records` | `source_system`, **`counts_toward_gpa` bool default true** | The one behaviour change in existing code — §7 |
| `credentials` | `source_system`, `issuing_body`, `original_issued_at` | `/verify` renders a historical state; `regenerate()` refused |
| `invoices` | `source_system` | Excluded from dunning and gateway |
| `payments` | `source_system` | Excluded from receipt generation and refund flow |

Index `source_system` on `course_offerings`, `enrollments`, `academic_records`, `invoices`.

### 4.3 Enum additions (string columns — code-additive, no migration)

- `UserStatus::Archived = 'ARCHIVED'`
- `LedgerReason::LegacyCarryForward = 'LEGACY_CARRY_FORWARD'`

`OtpPurpose` needs **nothing new** — account claim reuses `EmailVerification`, which already drives
`auth.verify` → `auth.password.create`. See §9.

---

## 5. The mapping engine

Two exports from two systems, each with columns nobody controls, is exactly the case where a
hardcoded parser rots. Mapping is a first-class, saved, reviewable object.

### 5.1 File profiling (on upload, before anything else)

Parse the header row and up to 200 data rows, and compute per column: inferred type, null
percentage, distinct count, min/max length, and five sample values. This is what makes the mapping
screen useful, and it answers Q2 (volume) for free — the profile reports the true row count on the
first upload, so nobody has to guess.

XLSX needs a worksheet picker and a header-row picker; Populi and Canvas exports both sometimes
carry a title row above the headers.

### 5.2 Suggestion, in three tiers

| Tier | Method | Confidence |
|---|---|---|
| 1 | Exact match against a per-source **synonym dictionary** — `Populi ID`→`legacy_id`, `SIS User ID`→`legacy_id`, `Final Grade`→`final_letter`, `Credits`→`credit_hours`, `Balance Due`→`balance_minor` | **High** |
| 2 | Normalised header match (lowercase, strip punctuation, collapse spaces, drop a trailing unit) | **Medium** |
| 3 | Value-shape heuristics — 99 % of values match an email pattern, a date format, a currency pattern, a 4.00-bounded decimal, a known grade letter set | **Low** |
| — | Nothing matched | **None** — the admin must map or explicitly ignore |

The synonym dictionary ships seeded with the real Populi and Canvas export headers and is editable
per source in the UI, so the second migration of the same export costs nothing.

### 5.3 Transforms

Attached per mapping, composable, each one previewed live against the sample values:

`trim` · `upper` / `lower` · `date(format)` · `split_name(surname_position)` · `money_to_minor` ·
`boolean(truthy_list)` · `constant(value)` · `concat(cols, sep)` · `lookup(grade_mapping)` ·
`normalize_arabic` · `strip_prefix/suffix` · `regex_extract(pattern)`

`split_name` matters more than it looks: Populi exports `Last, First Middle` while Canvas exports a
single `name` field, and Arabic names do not split on "the last token is the surname". The transform
takes an explicit surname-position option and previews the result on real rows.

`money_to_minor` parses a decimal **string** and multiplies by 100 with integer arithmetic (rule 3).
More than two decimal places is `E_MONEY_PRECISION` — an error, never a silent rounding.

### 5.4 Profiles

The finished mapping saves as an `import_mapping_profile`. The next file from the same source and
entity type loads it in one click, shows a diff if the headers have changed, and flags any column
that has appeared or vanished since. This is what makes the fix-and-re-import cycle (guaranteed by
D6) survivable.

### 5.5 AI-assisted mapping — deferred to L8, off by default

The project already has an `AiClient` interface (`app/Services/Ai/AiClient.php`) with a
null-returning Gemini implementation that degrades gracefully when unconfigured. AI mapping adds one
method to that interface — `suggestFieldMapping(array $schema): ?array` — and inherits the same
graceful degradation: no key, no suggestions, deterministic tiers 1–3 still work.

Three hard constraints, all of them non-negotiable:

1. **It is a proposal, never a commit.** The output lands in the same mapping screen as tier 1–3
   suggestions, marked `origin = AI` with its confidence and rationale, and a human approves every
   row. `proposed_by` on the profile records that AI was involved, and the approval is audited.
2. **No student PII leaves the building.** The request carries column *headers*, inferred types,
   null percentages, distinct counts and **masked** samples — `a***@g***.com`, `Xxxx Xxxx`,
   `3.4_` — plus the target-field catalog. Never raw names, emails, phone numbers, addresses,
   national ids or balances. A test asserts the outbound payload matches an allowlist shape.
3. **Off unless switched on.** Setting `import.ai_mapping_enabled`, default `false`, with the
   masking policy shown in the UI before it can be enabled.

Worth being honest about the value: on Populi and Canvas exports, whose headers are stable and
documented, tier 1 will map most columns outright. AI mapping earns its place on the *messy*
files — a registrar's hand-maintained spreadsheet of old diplomas — not on the clean ones. That is
why it is L8 and not L1.

---

## 6. Identity

`users.email` is `unique` and NOT NULL, and rule 5 forbids altering it:

- Real email → used as-is.
- Alumnus with no email → `legacy.<source>.<legacy_id>@no-email.invalid`, `email_verified = false`,
  `password_hash = null`, `status = ARCHIVED`. The mail layer gains one guard refusing any send to
  `@no-email.invalid`, with a test.
- **Active** student with no email → `E_ACTIVE_NO_EMAIL`, hard error, row blocked. An active student
  without a reachable address cannot claim an account, and pretending otherwise strands a real
  person.

### Matching ladder

| Rung | Rule | Outcome |
|---|---|---|
| 1 | `import_links` hit on `(source, 'user', legacy_id)` | Same person. Update in place. |
| 2 | **Canvas `SIS User ID` = an already-imported Populi id** | Same person. This is the crosswalk; it should resolve the overwhelming majority of Canvas rows. |
| 3 | Exact normalised email match against an existing user | Auto-link. |
| 4 | Exact match on legacy student number where both sides carry one | Auto-link. |
| 5 | Normalised `first + last + date_of_birth`, DOB present **and** the triple unique school-wide | Auto-link. |
| 6 | Anything else — name similarity, name + phone, name without DOB | **Never** auto-linked. Queued in `import_merge_candidates`. |

Arabic normalisation for rung 5: strip tashkeel, normalise alef/hamza forms and ta-marbuta, collapse
whitespace. **Never transliterate** — transliteration invents matches that are not there.

Two *existing native* SPIMS users are never merged by the importer; that is a separate feature (Q8).

**Import Populi first, then Canvas.** Rung 2 only works if the Populi ids already exist. This
ordering is enforced in the UI: a Canvas batch cannot be committed before a Populi `STUDENT` batch
has been.

---

## 7. Academic records, GPA and transfer credit

### Per legacy course result

1. A shadow `course_offering` for `(legacy course, legacy term)` — created once, reused.
   `status = Archived`, `source_system` set, no weeks, no content, no gradebook components.
2. An `enrollment` on it — `status = Completed` / `Withdrawn`, `grade_status = Locked`,
   `final_percent` / `final_letter` / `final_gpa_points` from the source's grade mapping,
   `progress_percent = 100`, `source_system` set.
3. An `academic_record` — `enrollment_id` → (2), `term` = the legacy term string, `credit_hours`
   **from the source, not from the matched SPIMS course**, and `counts_toward_gpa = false` for every
   legacy row at import time regardless of the mapping.

### The one change to existing code

`GradebookService::refreshGpa()` currently sums every fulfilment's record. It gains one filter:

```php
->where('counts_toward_gpa', true)
```

Native records default to `true`, so behaviour for every existing student is unchanged — **provable
by running the existing suite untouched.** That single filter delivers three things:

- Legacy results cannot move a live GPA (D3).
- Transfer credit becomes expressible (D7): a registrar attaches a legacy record to a
  `program_requirement_fulfillment` — credit granted, grade excluded — which is the standard
  registrar rule.
- A registrar can later *promote* one record to count toward GPA, through an audited service method
  rather than a data fix.

### What the student sees

`TranscriptController` and the degree audit gain a **Prior study** section: legacy records grouped
by source and term, each labelled with the source, the original letter and the original scale; and
the `legacy_academic_summaries` figure rendered as *"GPA 3.42 / 4.00 as recorded by Populi, as of
2024-06-30"* — beside the SPIMS GPA, never averaged into it.

---

## 8. Finance

Per student, per currency, at one declared cutover date:

- **Owed > 0** → one `Invoice`, `source_system` set, `status = Open`, one `InvoiceLine`
  *"Balance carried forward from Populi as of <date>"*, `offering_id = null`, `total_minor` integer.
- **Credit > 0** → one `WalletTransaction`, `kind = Money`, `direction = Credit`,
  `reason = LegacyCarryForward`, against the student's `WalletAccount` (created if absent).
- **Zero** → nothing. No empty invoices.

**Hard gate:** the batch declares `control_totals` — rows, distinct students, and total minor units
per currency. **Commit is refused unless the computed sum matches exactly.** Cheap, because money is
already integer minor units, and it is the single most valuable control in the feature.

Legacy invoices are excluded from `DunnOverdueInstallmentsCommand` and from the gateway until a
registrar opts a cohort in (Q9).

---

## 9. Account activation for active students

D8's live half. It reuses the existing auth flow rather than inventing one:

```
import commits          → user row, status = PENDING, password_hash = null,
                          import_account_claims row, status = QUEUED
registrar reviews cohort→ headcount reconciled against the term roster
registrar sends invites → EmailVerification OTP via the existing OtpService,
                          new email template legacy_account_claim (ar/en/fr),
                          claim row → SENT
student clicks          → auth.verify → auth.password.create  (both already exist)
password set            → user status ACTIVE, claim row → CLAIMED
```

Operational rules:

- **Invitations are never sent by the import commit.** Commit is silent (§10). Sending is a separate,
  explicit, permissioned action on a reviewed cohort — because the commit will be run more than once
  and nobody should be emailed twice by a re-run.
- Throttled in batches with a configurable rate, because a few hundred simultaneous sends to stale
  addresses is a deliverability event.
- Bounces mark the claim `BOUNCED` and surface in the activation screen as a worklist, not a
  silent failure.
- Reminders are manual, capped, and counted.
- An unclaimed account stays `PENDING` and cannot log in. Nothing expires silently.

---

## 10. Pipeline

```
upload → PROFILE → MAP → VALIDATE → DRY RUN → COMMIT → RECONCILE   ( → ROLLBACK )
         columns,  §5     rules,     full      chunked,  control      until
         types,           grades,    diff,     queued,   totals,      sealed
         samples          identity   0 writes  audited   per source
```

**Profile** parses and measures. **Map** produces or loads a profile (§5). **Validate** applies
mappings, grade tables, catalog resolution and the matching ladder, writing `messages` with stable
codes:

`E_UNMAPPED_GRADE` · `E_MONEY_PRECISION` · `E_DUPLICATE_NATURAL_KEY` · `E_ACTIVE_NO_EMAIL` ·
`E_REQUIRED_FIELD_MISSING` · `E_BAD_DATE` · `E_UNKNOWN_PROGRAM` · `E_CANVAS_BEFORE_POPULI` ·
`W_AMBIGUOUS_IDENTITY` · `W_UNKNOWN_TERM` · `W_CREDIT_HOURS_DIFFER` · `W_FILLED_FROM_CANVAS` ·
`W_NO_EMAIL_ALUMNUS`

Errors block the row; warnings do not. The full list downloads as CSV in the registrar's locale.

**Dry run** executes the entire commit path inside a transaction that is always rolled back, and
reports the diff. A dry run reporting zero writes for a non-empty file is itself an error.

**Commit** is chunked (1 000 rows), queued above 5 000, each chunk wrapped by
`AuditLogWriter::withAudit()` (rule 2) with **the acting admin as actor** — accountability for a mass
write matters more than tidiness. Per-row audit rows would run to millions; `import_rows` is the
per-row record, immutable after commit, and `PruneAuditLogsCommand` must not touch the import tables.

**Side effects are suppressed.** An `ImportContext::runSilently()` guard, honoured by
`NotificationService`, the communications dispatcher, the receipt/PDF renderer and the credential
issuer, so a commit sends zero mail, writes zero `communication_logs`, renders zero PDFs and charges
nothing. Completion-criteria evaluation and `AcademicStandingService::apply()` defer to one
post-batch pass. Asserted by test, not by inspection.

**Rollback** is available until `sealed_at` (default 30 days, a `Setting`). It reverses `CREATE`s and
restores `before_snapshot` for `UPDATE`s, and is **refused** when imported data is now referenced by
native data — a legacy record attached to a fulfilment, a legacy invoice with a native payment, a
claimed account. The refusal names the blocking rows rather than failing vaguely.

---

## 11. UI

Read [`docs/design-system.md`](design-system.md) before any markup. Tokens only; logical properties
only; three breakpoints verified (360 / 768 / 1440); every string in `lang/{ar,en,fr}/import.php`.

> **Clickable prototype:** <https://claude.ai/code/artifact/337221f7-9802-45f9-8546-98d4073ec654>
> Every screen below, built on the real SPIMS theme tokens, with working light/dark and RTL
> toggles. The mapping screen (§11.5) is fully interactive — change a target field or a transform
> and watch the preview, the counters and the required-field guard respond. It is a visual spec,
> not shipping code; the Blade implementation is the deliverable.

### 11.1 New shared component

The wizard needs a linear stepper, and the design system has no such component (`tabs` is
non-linear, `timeline` is vertical and stateless). **`<x-stepper>` is new shared surface**, so per
CLAUDE.md the phase that builds it must explicitly own `resources/views/components/`, and
`docs/design-system.md` gains a `### stepper` entry in the same change.

```blade
<x-stepper id="import-wizard" :current="3" :steps="[
    ['key' => 'upload',   'label' => __('import.step_upload'),   'href' => ..., 'state' => 'done'],
    ['key' => 'map',      'label' => __('import.step_map'),      'href' => ..., 'state' => 'done'],
    ['key' => 'validate', 'label' => __('import.step_validate'), 'href' => ..., 'state' => 'current'],
    ['key' => 'preview',  'label' => __('import.step_preview'),  'state' => 'upcoming'],
    ['key' => 'commit',   'label' => __('import.step_commit'),   'state' => 'upcoming'],
]" />
```

States `done | current | upcoming | blocked`. A `done` step is a link back; `upcoming` is inert.
RTL: order flips with `flex-direction` from the document direction, connector uses `inset-inline`.
Below `md` it collapses to `Step 3 of 5 — Validate` with a `<x-progress>` bar, because five labelled
nodes do not fit at 360 px.

### 11.2 Screen map

| # | Route | Permission | Purpose |
|---|---|---|---|
| 1 | `/admin/imports` | `import.view` | Hub: sources, batches, health |
| 2 | `/admin/imports/sources` | `import.configure` | Populi & Canvas config |
| 3 | `/admin/imports/sources/{source}/grades` | `import.configure` | Grade mapping grid |
| 4 | `/admin/imports/sources/{source}/synonyms` | `import.configure` | Header synonym dictionary |
| 5 | `/admin/imports/create` | `import.stage` | **Wizard 1** — upload & profile |
| 6 | `/admin/imports/{batch}/map` | `import.stage` | **Wizard 2** — field mapping |
| 7 | `/admin/imports/{batch}/validate` | `import.stage` | **Wizard 3** — validation report |
| 8 | `/admin/imports/{batch}/preview` | `import.stage` | **Wizard 4** — dry-run diff |
| 9 | `/admin/imports/{batch}/commit` | `import.commit` | **Wizard 5** — confirm & run |
| 10 | `/admin/imports/{batch}` | `import.view` | Batch receipt, reconciliation, rollback |
| 11 | `/admin/imports/{batch}/rows` | `import.view` | Row inspector |
| 12 | `/admin/imports/merges` | `import.merge_resolve` | Identity review queue |
| 13 | `/admin/imports/activation` | `import.activate` | Active-student account cohort |
| — | `/transcript`, `/admin/users/{user}` | existing | Prior-study section, provenance note |

Routes append under a new `// --- TRACK: legacy-import ---` anchor at the end of `routes/web.php`.
The file is never reordered.

### 11.3 Screen 1 — Import hub

```
┌─────────────────────────────────────────────────────────────────────────┐
│ <x-page-header  eyebrow="Administration"  title="Data import"           │
│                 subtitle="Populi and Canvas → SPIMS">                   │
│                              [ Configure sources ]  [ + New import ]    │
└─────────────────────────────────────────────────────────────────────────┘
  ┌──────────────┐┌──────────────┐┌──────────────┐┌──────────────┐
  │<x-stat>      ││<x-stat>      ││<x-stat>      ││<x-stat>      │
  │ Students     ││ Results      ││ Pending      ││ Accounts     │
  │ imported     ││ imported     ││ merges       ││ claimed      │
  │ 1,284        ││ 9,431        ││ 17    ⚠      ││ 212 / 340    │
  └──────────────┘└──────────────┘└──────────────┘└──────────────┘
  ┌── <x-card variant="quiet"> ── Source readiness ──────────────────────┐
  │  Populi   ● Configured · 42 grade mappings · 3 batches committed     │
  │  Canvas   ● Configured · blocked until Populi STUDENT committed  ✓   │
  └──────────────────────────────────────────────────────────────────────┘
  ┌── <x-card variant="panel"> ── Batches ───────────────────────────────┐
  │ <x-data-table> Source │ Entity │ Pop. │ Rows │ Status │ When │ By    │
  │  Populi  STUDENT  Alumni  1,284  ● Committed   12 Mar  R. George  ›  │
  │  Populi  RESULT   —       9,431  ● Committed   12 Mar  R. George  ›  │
  │  Canvas  RESULT   —         214  ▲ Validated   13 Mar  R. George  ›  │
  │  Populi  FINANCE  —         340  ○ Draft       13 Mar  R. George  ›  │
  └──────────────────────────────────────────────────────────────────────┘
```

`<x-status-badge>` maps batch status onto the existing tone table; `committed` → info,
`draft` → warning, `failed`/`rolled_back` → danger. Empty state uses `<x-empty-state>` with
`icon="bi-inbox"` and a single "Start with Populi students" call to action, because the correct
first action is never ambiguous.

### 11.4 Screen 5 — Wizard 1, upload

```
  <x-stepper current=1 …>

  ┌── <x-card variant="panel"> ──────────────────────────────────────────┐
  │  <x-field label="Source">   ( ) Populi   ( ) Canvas                  │
  │  <x-field label="What is in this file?">                             │
  │        [ Students ▾ ]  Students · Programs · Course results ·        │
  │                        Diplomas · Balances · Identity crosswalk      │
  │  <x-field label="Population">  ( ) Alumni   ( ) Currently studying   │
  │        hint: "Currently studying" requires a real email on every row │
  │  <x-file-drop name="file" accept=".csv,.xlsx" :max-size="…">         │
  └──────────────────────────────────────────────────────────────────────┘

  ── after upload, the profile appears inline ──
  ┌── <x-card variant="quiet"> ── File profile ──────────────────────────┐
  │  legacy-students.xlsx · 1,284 rows · 18 columns · sheet [ Sheet1 ▾ ] │
  │  header row [ 1 ▾ ]                                                  │
  │  ┌────────────────────────────────────────────────────────────────┐  │
  │  │ Column          Type     Empty   Distinct  Samples             │  │
  │  │ Populi ID       integer   0%      1,284    10432, 10433, …     │  │
  │  │ Last, First     text      0%      1,281    Boutros, George; …  │  │
  │  │ Email           email     6%      1,207    g.b@example.org; …  │  │
  │  │ Birthdate       date     11%      1,102    14/03/1994; …       │  │
  │  └────────────────────────────────────────────────────────────────┘  │
  │                                           [ Continue to mapping → ]  │
  └──────────────────────────────────────────────────────────────────────┘
```

The profile table answers the volume question on first contact and shows the registrar what the file
actually contains before a single decision is made. Duplicate `file_hash` shows an inline warning
with a link to the earlier batch and a "re-upload anyway" checkbox.

### 11.5 Screen 6 — Wizard 2, field mapping · **the centrepiece**

```
  <x-stepper current=2 …>

  ┌── <x-toolbar> ───────────────────────────────────────────────────────┐
  │ start: 18 columns · 14 mapped · 3 ignored · 1 unmapped ▲             │
  │ end:   [ Load profile ▾ ]  [ ✨ Suggest with AI ]  [ Save profile ]  │
  └──────────────────────────────────────────────────────────────────────┘

  ┌── <x-card variant="panel"> ──────────────────────────────────────────┐
  │  SHEET COLUMN                    →   SPIMS FIELD          TRANSFORM  │
  │ ┌──────────────────────────────┐   ┌────────────────────┐ ┌────────┐ │
  │ │ Populi ID          [High]    │ → │ Legacy id       ▾ ▪│ │ trim ▾ │ │
  │ │ integer · 0% empty           │   │ required           │ └────────┘ │
  │ │ 10432 · 10433 · 10434        │   └────────────────────┘            │
  │ │                          preview → 10432                           │
  │ ├──────────────────────────────┤   ┌────────────────────┐ ┌────────┐ │
  │ │ Last, First        [Medium]  │ → │ Name (split)    ▾ ▪│ │split ▾ │ │
  │ │ text · 0% empty              │   │ → first + last     │ │surname │ │
  │ │ Boutros, George              │   └────────────────────┘ │ first ▾│ │
  │ │            preview → first "George"  last "Boutros"     └────────┘ │
  │ ├──────────────────────────────┤   ┌────────────────────┐            │
  │ │ Birthdate          [Low]     │ → │ Date of birth   ▾ ▪│ │date ▾  │ │
  │ │ date · 11% empty             │   │ optional           │ │ d/m/Y  │ │
  │ │ 14/03/1994                   │   └────────────────────┘ └────────┘ │
  │ │            preview → 1994-03-14   ✓ 1,102 of 1,284 parse           │
  │ ├──────────────────────────────┤   ┌────────────────────┐            │
  │ │ Advisor notes      [None] ▲  │ → │ — not mapped —  ▾  │            │
  │ │ text · 78% empty             │   │ [ Ignore column ]  │            │
  │ └──────────────────────────────┘   └────────────────────┘            │
  └──────────────────────────────────────────────────────────────────────┘

  ┌── <x-card variant="quiet"> ── Required fields not yet mapped ────────┐
  │  ▲ Email — required for the "currently studying" population          │
  └──────────────────────────────────────────────────────────────────────┘
                                     [ ← Back ]  [ Validate 1,284 rows → ]
```

Behaviour:

- **Live preview per row.** Every transform change re-renders the preview from the profiled sample
  values, plus a parse-success count across the whole file for lossy transforms (dates, money,
  grades). A registrar should never discover a date-format mistake at validation.
- **Confidence chips** — `High` / `Medium` / `Low` / `None` as `<x-badge>`, with `None` carrying a
  warning tone. Sorting defaults to lowest confidence first, so attention lands where it is needed.
- **Ignoring is a decision.** An unmapped column blocks Continue until explicitly ignored. Silent
  dropping is how a column of balances goes missing.
- **Required-field guard.** The panel at the bottom lists unmapped required targets and is the only
  thing standing between the admin and the next step. Required-ness is population-aware — `email` is
  required for `ACTIVE`, optional for `ALUMNI`.
- **AI button** is present but disabled with an explanatory tooltip until L8 ships and the setting is
  on. When it runs: a loading state, then the same rows repopulated with `origin = AI` chips and a
  rationale tooltip per row. Nothing is applied without the admin pressing Continue.
- **Phone (360 px):** the three-column grid collapses to one card per sheet column — header and
  samples on top, target select, transform select, preview below. The arrow becomes a downward
  chevron. This is a genuinely usable phone layout and should be built, not bolted on.
- **RTL:** the `→` between column and field is direction-aware (`bi-arrow-right` / `bi-arrow-left`
  selected from the document direction, not mirrored by CSS transform, which breaks the icon font).

### 11.6 Screen 7 — Wizard 3, validation

```
  <x-stepper current=3 …>
  ┌──────────┐┌──────────┐┌──────────┐┌──────────┐
  │ Valid    ││ Warnings ││ Errors   ││ Skipped  │
  │ 1,240    ││ 31       ││ 13   ▲   ││ 0        │
  └──────────┘└──────────┘└──────────┘└──────────┘

  ┌── <x-card variant="panel"> ── Issues by type ────────────────────────┐
  │ ▼ E_ACTIVE_NO_EMAIL          9 rows   Active student has no email    │
  │     rows 114, 227, 388 …                        [ View rows ]        │
  │ ▼ E_BAD_DATE                 4 rows   Birthdate did not parse        │
  │     "14-Mar-94" — expected d/m/Y      [ Fix mapping ] [ View rows ]  │
  │ ▶ W_NO_EMAIL_ALUMNUS        22 rows   Synthetic address will be used │
  │ ▶ W_CREDIT_HOURS_DIFFER      9 rows   Sheet 3, SPIMS course 4        │
  └──────────────────────────────────────────────────────────────────────┘
        [ Download issues CSV ]   [ ← Back to mapping ]   [ Dry run → ]
```

Every error group offers the action that actually fixes it: `E_BAD_DATE` links straight back to that
column's transform, not to a generic help page. Errors do not block the dry run — they block those
*rows*; the count of rows that will be skipped is shown on the next screen and must be acknowledged.

### 11.7 Screen 8 — Wizard 4, dry run

```
  <x-stepper current=4 …>
  ┌── <x-card variant="panel"> ── What will happen ──────────────────────┐
  │  Users          1,198 created   42 linked to existing   0 updated    │
  │  Programs       1,284 student-program rows                           │
  │  Merge queue    17 rows need a human decision            [ Review ]  │
  │  Skipped        13 rows with errors — will not be imported           │
  └──────────────────────────────────────────────────────────────────────┘
  ┌── <x-card variant="quiet"> ── Control totals ────────────────────────┐
  │                         Declared        Computed       │             │
  │  Rows                   1,284           1,271          ▲ differs     │
  │  Distinct students      1,284           1,271          ▲ differs     │
  │  Total owed (EGP)       <x-money>       <x-money>      ✓ match       │
  │                                                                      │
  │  ▲ Totals differ by 13 — the rows with errors. Acknowledge to        │
  │    continue, or go back and fix them.        [ ] I acknowledge       │
  └──────────────────────────────────────────────────────────────────────┘
                                        [ ← Back ]  [ Commit import → ]
```

For a `FINANCE` batch the money row is a **hard gate** (§8): mismatch disables Commit outright, with
no acknowledge checkbox. Academic mismatches are acknowledgeable; money is not.

### 11.8 Screen 9 — Wizard 5, commit

`<x-confirm-dialog tone="danger">` requiring the word `COMMIT` typed, restating the four numbers
from the dry run, then a progress screen:

```
  ┌── <x-card variant="panel"> ──────────────────────────────────────────┐
  │  Importing Populi students…                                          │
  │  <x-progress :value="63" label="817 of 1,284 rows" />                 │
  │  Started 14:02 · chunk 9 of 13                                       │
  │  Do not close this page. The import continues if you do.             │
  └──────────────────────────────────────────────────────────────────────┘
```

Alpine polling against a small JSON endpoint reading `import_batches.commit_progress` — the same
pattern S9 already uses for live quiz, and it keeps rule 8 (no npm build step). On completion the
page redirects to the batch receipt.

### 11.9 Screen 10 — Batch receipt

What was created, with links; the reconciliation table; the audit entry; and the rollback control:
`<x-confirm-dialog tone="danger">` that either rolls back or, when blocked, lists exactly which
native references prevent it. After `sealed_at` the button is replaced by "Sealed on <date> — roll
back is no longer available", which is information, not a disabled mystery button.

### 11.10 Screen 12 — Merge queue

```
  ┌── <x-card variant="panel"> ── 17 need a decision ────────────────────┐
  │  ┌─ From Canvas ──────────────┐   ┌─ Existing in SPIMS ────────────┐ │
  │  │ <x-avatar> George Boutros  │   │ <x-avatar> Girgis Boutros      │ │
  │  │ g.boutros@example.org      │   │ g.boutros@example.org          │ │
  │  │ DOB —                      │   │ DOB 1994-03-14                 │ │
  │  │ Canvas id 88213            │   │ Populi id 10432 · 6 records    │ │
  │  └────────────────────────────┘   └────────────────────────────────┘ │
  │  Matched on: email          Score 0.82                               │
  │  [ Same person — merge ]  [ Different — create new ]  [ Skip ]       │
  └──────────────────────────────────────────────────────────────────────┘
```

Side-by-side, differing fields highlighted, the matched-on basis stated plainly. Every decision is
audited and reversible while the batch is unsealed. Keyboard-navigable, because seventeen is a
worklist and a hundred is a shift.

### 11.11 Screen 13 — Account activation

```
  ┌──────────┐┌──────────┐┌──────────┐┌──────────┐
  │ Eligible ││ Invited  ││ Claimed  ││ Bounced  │
  │ 340      ││ 340      ││ 212      ││ 4    ▲   │
  └──────────┘└──────────┘└──────────┘└──────────┘
  <x-tabs> All · Not invited · Invited · Claimed · Bounced

  <x-data-table> ☐ │ Student │ Email │ Program │ Status │ Invited │ …
  [ Send invitations to 128 selected ]   [ Preview email ▾ ar en fr ]
```

Selection-based, with a preview of the actual email in each locale before anything is sent, a
throttle notice showing the send rate, and `BOUNCED` as a worklist with an inline email correction
that re-queues. The headcount at the top is what the registrar reconciles against the term roster.

### 11.12 Student-facing

- **Transcript** gains a `Prior study` section — legacy records grouped by source and term, each
  with the original letter and scale, and the attested legacy GPA rendered beside the SPIMS GPA with
  both labelled. Both figures use `<x-badge>`/token classes, never a bare coloured span.
- **Profile** gains one quiet line: *"Records migrated from Populi on 12 March 2026."*
- Neither surface is added to the API without a row in
  [`docs/api-web-parity-matrix.md`](api-web-parity-matrix.md) (parity rule).

### 11.13 Localisation

All strings in a new `lang/{ar,en,fr}/import.php` — a new per-step file, not an append to a shared
one. Validation codes are keys (`import.error.E_ACTIVE_NO_EMAIL`), never sentences, so the issue
list renders in the registrar's locale and the CSV download matches. Arabic is primary and RTL; the
mapping screen is the hardest RTL surface in the feature and should be reviewed at 360 px in Arabic
before it is called done. `LocaleParityTest` gates.

---

## 12. Permissions

Seven new keys in `config/permissions.php`. **None is offering-scoped** — they are school-wide by
nature — so all seven are deliberately absent from `permission_scopes.php` `offering_scoped`, and
that absence is recorded here to satisfy hard rule 1. `docs/role-matrix.md` gains a row each.

| Key | SUPER | ADMINISTRATIVE | ACADEMIC | FINANCIAL | INSTRUCTOR / TA / STUDENT |
|---|---|---|---|---|---|
| `import.view` | bypass | R | R | R | — |
| `import.configure` | bypass | F | — | — | — |
| `import.stage` | bypass | F | F | — | — |
| `import.commit` | bypass | F | — | — | — |
| `import.rollback` | bypass | F | — | — | — |
| `import.merge_resolve` | bypass | F | — | — | — |
| `import.activate` | bypass | F | — | — | — |

A `FINANCE` batch additionally requires `finance.manage`, so a registrar cannot commit money. An
`ACTIVE`-population batch additionally requires `users.manage`, because it creates login-capable
accounts.

---

## 13. Phases

| Phase | Scope | Done when |
|---|---|---|
| **L0** | Intake contract: exact column spec per entity per source, agreed with whoever runs the Populi and Canvas exports. `import_sources`, `import_grade_mappings`, synonym dictionary, screens 2–4. | Both real exports are in hand and profile cleanly; Populi's grade scale is entered and renders as a conversion table |
| **L1** | Batch spine + **mapping engine** + wizard screens 1, 5–11. Proven on `STUDENT` only. Owns `components/` for `<x-stepper>` and updates `docs/design-system.md`. | A student file maps, validates, dry-runs, commits and rolls back; importing twice creates rows once; zero mails sent; the mapping screen works at 360 px in Arabic |
| **L2** | Identity: ladder rungs 1–6, Arabic normalisation, Canvas crosswalk, merge queue (screen 12) | A person in both systems lands as one user with two links via `SIS User ID`; an ambiguous pair reaches the queue and never auto-merges |
| **L3** | Shadow catalog, student programs, course results, `legacy_academic_summaries`, the `counts_toward_gpa` filter | A live student's `cached_gpa` is byte-identical before and after importing legacy results for them; shadow offerings appear in no catalog, roster, gradebook or report |
| **L4** | Transcript / degree-audit / profile surfacing; registrar promotion to transfer credit (D7) | A student sees prior study and both GPAs, correctly labelled, in all three locales; a promoted record moves GPA and is audited |
| **L5** | **Active-student activation** (§9, screen 13) | A test cohort of ten receives an invitation, claims accounts, and logs in; a re-commit of the same batch sends nothing |
| **L6** | Finance opening balances + the control-total gate | Totals mismatched by one piastre and Commit is refused; matched totals commit and the AR report balances per source per currency |
| **L7** | *Conditional* — mid-term cutover (V2, §3): current-term enrolments on live offerings + Canvas gradebook partials | Only if the calendar forces a mid-term go-live |
| **L8** | *Optional* — legacy credentials + `/verify` historical state, and AI-assisted mapping (§5.5) | A migrated diploma verifies as historical and cannot be reissued under a SPIMS serial; AI proposes a mapping for a messy sheet and a human approves every row |

**Critical path for go-live: L0 → L1 → L2 → L3 → L5 → L6.** L4 can trail by a week (students can be
enrolled before they can view a pretty transcript). L7 only if forced. L8 never blocks anything.

The riskiest phase is **L5**, not any of the data phases — it is the only one whose failure is
visible to students on day one.

---

## 14. Open questions

Recommended default in bold. Answer any and its phase is unblocked; the rest proceed on defaults.

**Sources**

1. **Does any official final grade exist only in Canvas, or is Populi complete?** If Populi is
   complete, the Canvas import collapses to an identity crosswalk file and L1–L3 shrink by roughly a
   third. **Default: assume Populi is complete, treat Canvas as crosswalk-only**, and revisit if the
   registrar finds gaps.
2. Is the Canvas `SIS User ID` actually populated with the Populi id? If the two were never
   integrated, rung 2 of the ladder collapses and the merge queue becomes the main event — which
   changes L2 from a week to a project.
3. **When is the cutover — between terms or mid-term?** **Default: between terms (V1).** Mid-term
   triggers L7.
4. Populi and Canvas both hold course codes. Which is the school's real catalog code going forward —
   or is SPIMS's catalog being rebuilt from scratch? Determines how many exact-match links §4.2
   produces versus how much stays shadow.

**Academic**

5. Legacy terms rarely map to SPIMS `semesters`. **Default: store the legacy term as free-text
   `academic_records.term` and create no `semesters` rows.**
6. Credit-hours conflict — Populi says 3, the matched SPIMS course says 4. **Default: the record
   keeps the legacy credits, the course keeps its own, and the row carries
   `W_CREDIT_HOURS_DIFFER`.**
7. Should `W` / `I` / `AU` / `P` rows appear on the transcript? **Default: yes, imported with
   `counts_toward_gpa = false`.** A transcript that hides withdrawals is not a transcript.
8. Two *native* SPIMS users turn out to be one person. **Default: out of scope** — the importer
   refuses and queues it. A real user-merge tool is its own feature.

**Active students**

9. What defines "currently enrolled and studying" in the Populi export — a status value, an entrance
   term, an active course enrolment? This is the filter that decides who gets a login, and getting
   it wrong strands real students. It should be a single named column, agreed in L0.
10. Do active students keep their existing email addresses, or is the school issuing new ones at
    cutover? New addresses change the claim flow from "verify the address we have" to "tell the
    student their new address by another channel first".
11. Who answers the phone on go-live day when a student cannot claim their account? Not a technical
    question, but L5 is not done without an answer.

**Financial**

12. Should imported opening balances be **collectable** — chased by dunning, payable through the
    gateway? **Default: visible and payable, excluded from automated dunning** until a registrar
    opts a cohort in.
13. One cutover date for money, or one per source? Populi holds the money, so **default: one date,
    Populi's.**
14. Any currency beyond EGP and USD in the Populi data? The `Currency` enum has exactly two;
    anything else needs a new case or a school-declared conversion rate. The importer will not
    invent an FX rate.
15. Do old receipt numbers need to survive for audit? Under D4 they do not come across. If auditors
    need them, the minimal addition is a note on the carried-forward invoice line, not a payment
    history.

**Privacy and operations**

16. Which personal fields actually come across? **Default: name, DOB, phone, country, preferred
    locale, legacy student number.** National id, addresses, family data and photographs are
    excluded unless someone names a use — SPIMS has no field for most of them, and adding them is a
    bigger privacy decision than an import.
17. Are any of these students minors, and does that change who may view the record?
18. Where do the export files live after import? They contain the school's entire student body.
    **Default: private storage, hashed, referenced from the batch, purged on seal** — not in the
    repo, not in `public/`.
19. **Is AI-assisted mapping acceptable at all, given it sends column headers and masked samples to
    an external API?** **Default: build it in L8, ship it off, and require the school to switch it
    on after reading the masking policy in the UI.** A *no* simply removes L8's second half.
20. Rollback window before a batch seals. **Default: 30 days**, configurable.

---

## 15. Risks

| Risk | Mitigation |
|---|---|
| A legacy import silently changes a live student's GPA or standing | `counts_toward_gpa = false` on every legacy row; `ImportGpaIsolationTest` asserts `cached_gpa` unchanged before/after |
| Thousands of emails fire on commit | `ImportContext::runSilently()`; invitations are a separate permissioned action on a reviewed cohort (§9); `ImportSilenceTest` |
| An active student cannot log in on day one | `E_ACTIVE_NO_EMAIL` is a hard error; headcount reconciled against the term roster; L5 rehearsed on a cohort of ten |
| Canvas overwrites good Populi data | Precedence by `import_sources.precedence`; Canvas may only fill empty fields, each flagged `W_FILLED_FROM_CANVAS`; Canvas batches blocked until Populi students commit |
| A column is silently dropped | Unmapped columns block Continue until explicitly ignored (§11.5) |
| Shadow catalog leaks into the public catalog or reports | `source_system IS NULL` filters plus `ImportCatalogIsolationTest` sweeping every catalog, roster, gradebook and report query |
| Money drifts by rounding | Integer minor units end to end (rule 3); >2 decimal places is a hard error; control totals gate the commit |
| A re-run duplicates the school | `import_links` unique on `(source, entity_type, legacy_id)`; `file_hash` refuses identical re-upload; `ImportIdempotencyTest` |
| Two people merged, or one split | Rungs 1–5 deterministic only; everything else queues; merges audited and reversible while unsealed |
| Student PII sent to an AI provider | Masked payload with an allowlist-shape test; off by default; policy shown before enabling |
| A migrated diploma looks SPIMS-issued | Namespaced serials, `issuing_body`, distinct `/verify` state, `regenerate()` refused |

---

## 16. Tests

New suite under `tests/Feature/Import/`, wired into `prompts/steps.tsv` per phase so
`./scripts/validate-step.sh` gates each one.

`ImportProfileTest` · `ImportMappingSuggestionTest` · `ImportTransformTest` (dates, Arabic name
split, money precision) · `ImportProfileReuseTest` · `ImportIdempotencyTest` · `ImportDryRunTest` ·
`ImportRollbackTest` · `ImportGpaIsolationTest` · `ImportTransferCreditTest` · `ImportSilenceTest` ·
`ImportFinanceReconciliationTest` · `ImportCatalogIsolationTest` · `ImportIdentityMatchTest` ·
`ImportCanvasCrosswalkTest` · `ImportPrecedenceTest` · `ImportMergeQueueTest` ·
`ImportAccountClaimTest` · `ImportPermissionTest` (every role × every new key, deny and allow) ·
`ImportAiPayloadRedactionTest` · `ImportCredentialVerifyTest` · `LocaleParityTest` (existing).

**The existing suite must pass unmodified after L3.** That is the proof that the
`counts_toward_gpa` filter changed nothing for native students.

---

## 17. As shipped (L0/L1 foundation)

What actually landed, in one place, so this document stays trustworthy as the feature grows.
Everything below is real, tested, and in production — not aspirational.

### Shipped

- **Schema**: `import_sources`, `import_grade_mappings`, `import_mapping_profiles`,
  `import_batches`, `import_rows`, `import_links`; `source_system` on `users` and
  `student_programs`; `UserStatus::Archived`.
- **Mapping engine**: file profiling (CSV native, XLSX via `phpoffice/phpspreadsheet`), three-tier
  suggestion (Populi/Canvas synonym dictionaries, normalised-header match, value-shape heuristics),
  nine composable transforms (`trim`, `lower`, `upper`, `date` with strict round-trip validation —
  a rollover like month 14 is caught, not silently accepted — `name_part_first`/`name_part_last`
  with `last_first`/`first_last` order, `normalize_arabic`, `constant`), and saved reusable mapping
  profiles.
- **Pipeline**: upload → map → validate → dry-run (executes the real commit path inside a
  transaction that is always rolled back — proven by `ImportBatchServiceTest`) → commit → rollback,
  for the `STUDENT` entity, wrapped in `AuditLogWriter::withAudit()`.
- **Identity, v1 slice**: matching ladder rungs 1 (`import_links`) and 3 (exact normalised email);
  a re-imported legacy id links instead of duplicating.
- **D8, both halves**: alumni import `ARCHIVED` with a synthetic `@no-email.invalid` address when
  no email is given and no password ever set; a currently-studying row with no email is a hard
  validation error (`E_ACTIVE_NO_EMAIL`), and a valid one imports `PENDING`, ready to claim through
  the **existing** `auth.verify` → `auth.password.create` flow — no new auth code was needed.
- **D2, exact-match half**: an optional `program_code` field attaches a `StudentProgram` only when
  it matches a program that already exists in the live catalog; an unmatched code warns
  (`W_UNKNOWN_PROGRAM_CODE`) and creates no program.
- **Rollback**: refuses once sealed (30 days), and per-row once the created account has a role, an
  enrollment, a financial record, or has been claimed (password set / email verified) — naming the
  reason rather than failing silently.
- **Silence**: committing sends no mail (proven by `Mail::fake()` + `assertNothingSent()` — there is
  no observer or notification wired to `User::create()` in this path, so it holds by construction,
  not by a suppression flag).
- **Permissions**: `import.view`, `import.configure`, `import.stage`, `import.commit`,
  `import.rollback`, all school-wide (absent from `permission_scopes.php` by design, documented
  there); an `ACTIVE`-population upload additionally requires `users.manage`.
- **UI**: hub, source + grade-mapping config, upload, mapping (per-row confidence badges, live
  Alpine-driven transform-option fields, a required-field guard that blocks Continue), a batch
  receipt with status-appropriate next steps, a dry-run report with a control-total row and an
  acknowledge gate, and a rollback control — built from the project's existing component library
  (`x-page-header`, `x-card`, `x-stat`, `x-status-badge`, `x-file-drop`, `x-empty-state`) rather
  than new shared components, and localised in `lang/{ar,en,fr}/import.php`.
- **Nav**: an "Import" tile on the Administrative Admin hub; a `role-matrix.md` section.
- **Tests**: 61 tests across 8 files — profiler, transforms (including the Arabic-normalisation and
  strict-date cases above), suggestions, the full pipeline via the real HTTP routes end to end, the
  permission matrix, source/grade-mapping CRUD, and lang-key parity for every string this feature
  added. The full pre-existing suite (1,172 tests) passes unmodified alongside it.

### Deliberately simplified for this pass

- **Commit runs synchronously** in the request, not queued. Fine at school scale; a batch in the
  tens of thousands of rows should move to a queued job first.
- **The Populi/Canvas synonym dictionary is code, not a database table** — not yet editable from
  the UI the way §5.2 of this plan describes. Cheap to add later; didn't block shipping the engine.
- **XLSX sheet selection is a text field**, not a populated dropdown — there is no AJAX round trip
  on upload to list worksheet names before the file is attached to a batch.
- **`program_code` is the only academic field wired up.** `legacy_gpa`, `honors`,
  `credits_earned`, and `entrance_term` are deliberately absent from the mapping target catalog
  rather than accepted and silently dropped — see §11.5's rule that an unmapped field must never be
  offered as if it did something.

### Not built yet (traces to later phases in §13)

As of §21, every phase through L6 has shipped: L2 (identity ladder + merge queue), L3/L4 (course
results, GPA isolation, shadow catalog, transcript surfacing), L5 (account-claim invitations) and L6
(finance opening balances) — see §18, §21, §19 and §20 respectively for their design notes. What
remains is only what was always conditional/optional in the phase plan:

- **L7 — mid-term cutover.**
- **L8 — legacy credentials and AI-assisted mapping.**

Neither is scheduled; both stay here as future options rather than committed next work.

---

## 18. L2 design notes — the rest of the identity ladder and the merge queue

Rungs 2, 4 and 5 and the merge queue (rung 6, screen 12) shipped on top of the L1 foundation in
§17. This section records the design decisions made while building them, additively — §17 stays
exactly as it was.

### 18.1 When rung 6 actually fires

A literal reading of §6's ladder table — "anything else... queued" — would send *every* row that
isn't an exact match to the merge queue, including the ordinary case for a first-time migration: a
person nobody in SPIMS has any record of at all. That would make the queue the main event for every
import rather than the exception the plan's own screen mock describes (17 of 1,284 rows, §11.10),
and it would silently break every L1 test that imports a brand-new alumnus with no prior SPIMS
counterpart — the existing suite passing unmodified is a hard bar (§16).

The rule actually implemented: a row reaches the merge queue only when there is a genuine reason to
suspect it might already exist —

- an **escalation**, carried forward from rung 4 or 5 finding *more than one* exact match (an
  ambiguous student number, or an ambiguous normalised name+DOB triple — e.g. twins); or
- a **looser signal** found once rungs 1-5 are exhausted with no escalation: an exact
  normalised-name match with no usable DOB, or PHP `similar_text` name similarity at or above a 60%
  floor.

A row with no signal at all under either of those — the ordinary case — is created directly, exactly
as before L2. This is also why two different rows created together in the *same* file must never be
compared against each other by the looser search: a shared surname between two unrelated siblings on
one sheet is common, would otherwise flag them as a possible duplicate of each other, and has nothing
to do with rung 6's actual job (catching a row that might duplicate someone *already in SPIMS*).
`ImportBatchService::applyRows()` tracks every user id created earlier in the same run and excludes
it from that row's candidate search.

The "no candidate — create new?" case the plan calls out (`candidate_user_id` null) is real and
handled gracefully end to end (the merge screen offers only "create new" / "skip" for it, and `merge`
is refused with a clear error) — it just isn't something the ladder produces on its own from a
plain, signal-free new row, for the reason above. `ImportIdentityLadderTest` builds it directly by
validating a batch (never committing it) and inserting the `import_merge_candidates` row itself,
exercising the resolution paths independently of how such a row would arise in practice (most
plausibly: a genuine ambiguity elsewhere prompts a registrar to look, and a *different* row in the
same worklist turns out to have nothing to go on).

### 18.2 The Canvas/SIS crosswalk and commit ordering

Rung 2 looks up `import_links` for `entity_type = 'user'` with the same `legacy_id`, a *different*
`source_id`, and a source of `kind = SIS`. On a hit it creates a new `import_links` row for the
current (LMS) source pointing at the same user, so a later re-import of the same Canvas file hits
rung 1 directly rather than re-walking the crosswalk.

"Import Populi first, then Canvas" (§6) is enforced in `ImportBatchService::commit()`: a batch whose
source is `kind = LMS` is refused with a localized `E_CANVAS_BEFORE_POPULI` message unless at least
one `STUDENT` batch from a `kind = SIS` source has already committed. It is a commit-time gate only —
mapping, validating and dry-running a Canvas batch ahead of Populi is harmless and useful for
rehearsal; only the real write is blocked.

### 18.3 Student number and DOB-triple matching

`users.student_number` is a new nullable, indexed column (native SPIMS users simply never populate
it). Rung 4 links only when exactly one existing user carries the incoming value; more than one is
an escalation (§18.1), never a silent pick.

Rung 5 normalises `first_name`/`last_name` for comparison with the same Arabic-normalisation rules as
§6 (strip tashkeel, unify alef/ta-marbuta variants, never transliterate) plus a lowercase+trim pass
that is a no-op on Arabic text, applied identically to the incoming row and every candidate read back
from `users` — the comparison is fair regardless of which side, if either, is Arabic.

### 18.4 What merge queue resolution actually does

`ImportBatchService::resolveMergeCandidate()` is the one code path behind all three actions on
screen 12, each wrapped in `AuditLogWriter::withAudit('import.merge_resolve', ...)`:

- **merge** — links exactly as rungs 2-5 would have (the same `linkUser()`/`attachProgram()` helpers),
  and is refused if the candidate is null (nothing to merge with).
- **reject → create new** — creates a user exactly as an unresolved row would have
  (`createUser()`), then links the new user to this source + legacy id.
- **skip** — audited, but changes nothing; the candidate stays `PENDING` for a later pass.

All three read the original `import_rows` normalized snapshot (via `batch_id` + `natural_key` =
`legacy_id`) rather than re-deriving fields from `payload_preview`, since the latter is the raw sheet
row kept only for the compare panel, not shaped like `normalized`.

---

## 19. L5 design notes — account-claim invitations

Screen 13 (§9) shipped on top of §17/§18. This section records the design decisions made while
building it, additively — §17 and §18 stay exactly as they were.

### 19.1 Who gets queued, and when

`ImportBatchService::queueAccountClaims()` runs once, at the end of `commit()`, after `applyRows()`
has already written every row's `import_rows.action`/`target_id`. It is a no-op for anything other
than a `STUDENT` batch with `population = ACTIVE` — an alumni batch never queues a claim, matching
D8: alumni get no portal login at all.

For an eligible batch, it collects every row whose action was `CREATE` or `LINK` and whose target user
is still `status = PENDING`, then `firstOrCreate`s one `import_account_claims` row per user —
deliberately `firstOrCreate`, not `updateOrCreate`: a claim already `SENT`, `BOUNCED` or `CLAIMED` by
an earlier batch keeps its own history and is never reset to `QUEUED` just because a later batch
happens to reference the same person (e.g. a Canvas batch linking to a user Populi already created and
queued).

### 19.2 Sending is a separate, throttled step

Queueing never sends mail — a registrar reviews the headcount on screen 13 first, then triggers
sending explicitly. Sending chunks the queued cohort (`import.claim_send_chunk_size`,
`import.claim_send_chunk_delay_ms` — both configurable, defaulting to values safe for the mail
provider's rate limit) rather than firing every invitation in one burst. Each claim transitions
`QUEUED` → `SENT` (or `BOUNCED` on a hard mailer failure) independently, so a failure partway through
a large batch never blocks the rows already sent or silently retries them.

### 19.3 Claiming reuses the existing auth flow

An invited student claims their account through the **existing** `auth.verify` → `auth.password.create`
flow (§17 already noted this needs no new auth code) — the claim link is only what routes them there
and marks the claim `CLAIMED` once the password is set. No password or token is ever imported or
generated by this service itself, per the original open-question decision: SPIMS always issues its
own.

---

## 20. L6 design notes — finance opening balances

D4 and §8 shipped on top of §17-§19. This section records the design decisions made while building
it, additively.

### 20.1 The hard gate has no override, by design

Every other control-total check in this feature (the `STUDENT` entity's declared-vs-computed row
count) has an acknowledge checkbox — a registrar can proceed past a mismatch once they've reviewed
why. `BALANCE` deliberately has none: `ImportBatchService::assertFinanceTotalsMatchExactly()` compares
declared vs. computed, per currency, to the minor unit, and refuses commit outright on any difference,
recomputed fresh at commit time rather than trusted from a possibly-stale dry run. This is not an
oversight carried over from the `STUDENT` path — it is the acceptance criterion §8 exists to state:
money is never imported on a "close enough" basis. A currency the registrar never declared a total for
is compared against zero, so real money in an undeclared currency still fails loudly.

### 20.2 What one committed row actually writes

Per valid row, independently: `owed_minor > 0` creates exactly one `Invoice` (status `Open`,
`source_system` set, one `InvoiceLine` describing the source and cutover date, no `offering_id`) and
`credit_minor > 0` creates exactly one wallet credit (`WalletService::credit()`, reason
`LEGACY_CARRY_FORWARD`) against the student's wallet. A row can do both. A row with neither is a
no-op (`ImportRowAction::Noop`), not an error — §8's "no empty invoices" rule.

### 20.3 Legacy invoices are real, but excluded from automated dunning by default

A legacy opening-balance invoice is a real, payable invoice from the student's side — it appears on
their statement and can be paid down like any other. `PaymentPlanService::dunnOverdue()` excludes any
invoice with a non-null `source_system` from the automated overdue-reminder sweep, because an invoice
dated at cutover is "overdue" only as an artifact of that date, not because the student missed a real
deadline. A registrar can still chase these manually; nothing about the exclusion prevents payment or
hides the balance.

### 20.4 BALANCE rollback is out of scope

`ImportBatchService::rollback()` now refuses outright (`import.rollback_unsupported_entity`) for any
batch whose `entity_type` is `BALANCE`. Reversing a `STUDENT` batch's created users is safe because nothing
external depends on them yet in the ordinary case; reversing a committed invoice or wallet credit is
not the same problem — the invoice may already carry a payment, the wallet credit may already have
been spent. That is a distinct feature with its own rules (crediting back what remains, voiding what
doesn't), not a copy of the `STUDENT` rollback's delete-the-user logic, and is deliberately deferred
rather than shipped half-safe.

### 20.5 Committing a BALANCE batch requires `finance.manage` in addition to `import.commit`

Mirroring the `ACTIVE`-population `STUDENT` commit's extra `users.manage` check (§17), a `BALANCE`
commit additionally requires `finance.manage` — held by `FINANCIAL_ADMIN`, not
`ADMINISTRATIVE_ADMIN`. A registrar who can stage and validate a finance batch still cannot commit
real money into the ledger on their own signature; a financial admin's sign-off is a second, separate
gate, exactly as the plan's permission table (§12) describes.

---

## 21. L3/L4 design notes — course results, GPA isolation, shadow catalog, transcript surfacing

Screens for a third entity type, `COURSE_RESULT`, and the transcript's "Prior study" section shipped
on top of §17-§20. This section records the design decisions made while building them, additively.

### 21.1 GPA isolation is enforced at write time, not read time

The plan's central acceptance criterion for L3 — a legacy course result must never move a student's
live GPA on import — is enforced the simplest possible way: every `AcademicRecord` a `COURSE_RESULT`
commit writes sets `counts_toward_gpa = false` unconditionally, regardless of what the grade mapping
itself says about the grade. `GradebookService::refreshGpa()` filters on this column, so a freshly
imported record is invisible to the live GPA calculation from the moment it exists — there is no
separate "hide legacy records from GPA" pass to keep in sync, and no way for the filter and the import
path to drift apart, because the filter is the only thing that ever reads the column.

A registrar moves one record into the live GPA deliberately, one at a time, via
`GradebookService::promoteToTransferCredit()` — the only code path that ever flips
`counts_toward_gpa` to `true`. This is D7 from §7: transfer credit is a human decision made per
record, never a side effect of the import itself.

### 21.2 The shadow catalog is find-or-create, keyed loosely on purpose

A `COURSE_RESULT` row's `course_code` reuses an existing live course on an exact code match; anything
else find-or-creates a shadow `Course` (`active = false`, `source_system` set) and, per (course,
`legacy_term`) pair, a shadow `CourseOffering` (`status = Archived`, `semester_id = null`,
`legacy_term` carrying the source's own term string verbatim — §14 Q5's default: no attempt to map a
legacy term onto a real `semesters` row). "Loosely keyed" here means `legacy_term` is free text, not a
foreign key into anything — two files that spell the same term differently create two shadow
offerings, which is an acceptable, correctable-later outcome rather than a blocking validation error,
consistent with the plan's general bias toward "import now, tidy later" for anything that isn't
identity or money.

Every native-facing surface that lists courses or offerings — the public catalog, the admin offerings
index, the enrollment roster picker, the live gradebook grid, headcount/grade reports — filters on
`active = true` / a real `semester_id`, so a shadow record is structurally invisible everywhere a
registrar or student would otherwise stumble onto it, without needing a bespoke "is this legacy"
check bolted onto each of those surfaces. `ImportCatalogIsolationTest` asserts this for every listed
surface directly, rather than trusting the pattern by inspection.

### 21.3 `legacy_academic_summaries` is a fact, not an input

The source system's own attested GPA, credits, and standing figures — §4.1 — are stored verbatim in
`legacy_academic_summaries`, on their own reported scale (`gpa_scale`, never converted or rescaled to
SPIMS's), and rendered on the transcript beside the live SPIMS GPA. The two numbers are never
combined, averaged, or reconciled against each other: the legacy figure is a historical fact about
what the source system once said, not an input to any SPIMS calculation, matching D3's "never merged
into" rule from §17.

### 21.4 Rollback is per-record, not per-course

A `COURSE_RESULT` row's rollback (`ImportBatchService::rollbackCourseResultRow()`) deletes its
`AcademicRecord`, any `ProgramRequirementFulfillment` it created, and the `Enrollment` it was posted
against — but leaves the shadow `Course`/`CourseOffering` in place. They are idempotent
find-or-create infrastructure that other rows, or a later batch, may already be sharing; deleting them
on one row's rollback would either orphan those other rows or require tracking cross-row references
that don't otherwise exist. Leaving them behind is harmless — §21.2's isolation makes them invisible
regardless of whether anything still points at them.

Rollback is refused per-record once that record has been promoted to transfer credit
(`counts_toward_gpa = true`): a promotion is a human decision that has already taken effect in the
live GPA, and reversing the import underneath it would silently move that GPA back without anyone
having decided to. This mirrors the `STUDENT` rollback's own rule (§17) that a record already put to
native use blocks its own reversal — same principle, applied to `COURSE_RESULT`'s specific kind of
"native use."
