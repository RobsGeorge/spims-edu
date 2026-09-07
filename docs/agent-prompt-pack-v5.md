# SPIMS — Agent Prompt Pack **v5**
### Corrected baseline · decomposed steps · validation gates after every step and wave

> **v5 changes vs v4**
> 1. **Every metric in PART 0 re-measured against the repo.** v4's numbers were materially wrong
>    (87 raw cards, not 54; 168 views, not 118; 92 icons, not 18). Scope estimates changed with them.
> 2. **A2 lane coverage was incomplete.** v4's seven lanes named only 126 of 168 views — 38 were
>    orphaned, including all of `auth/` and `errors/`. Rebalanced to **9 lanes of 16–20**.
> 3. **Steps 9–12 were mis-scoped as greenfield.** Events, Surveys, Projects and Live Quiz all
>    already have student routes and views. They are now **audit-then-complete**, not build-from-zero.
> 4. **Step 6 rules 2 and 3 are already implemented.** Rewritten to target only what is actually
>    missing, so the agent does not "re-add" working code.
> 5. **A1 decomposed into A1.1–A1.6**, each with its own validation gate, so the bottleneck has
>    checkpoints instead of being one unreviewable pass.
> 6. **Steps 13, 8A and A2-a decomposed** into substeps for the same reason.
> 7. **Validation harness added** (PART 6): `scripts/validate-step.sh` and `scripts/validate-wave.sh`,
>    a per-step test contract, and a gate that must pass before any lane merges.
> 8. **The `Stop` hook no longer runs the full suite.** It runs the step's declared scope; the full
>    suite runs once per wave at the merge gate.

---

# PART 0 — Corrected baseline

Measured on the working tree, not estimated. **v4's figures are in the third column; several were far
enough off to change the plan.**

| Signal | **Actual** | v4 claimed | Consequence |
|---|---|---|---|
| `card border-0 shadow-sm` | **87** across **57 files** | 54 | A2 migration is ~60% larger than planned |
| Total Blade views | **168** (164 + 4 components) | 118 | A2 lanes were sized for a smaller repo |
| Views using `x-page-header` | **67** | 34 | Adoption is *better* than v4 assumed |
| Files containing a bare `<h1>` | **72** | 84 implied | Still the single most common legacy pattern |
| Blade components | **4** | 4 ✅ | `confirm-dialog`, `empty-state`, `page-header`, `status-badge` |
| Bootstrap Icon (`bi-*`) instances | **92** | 18 | Portal is *not* icon-free; vocabulary needs to unify existing use, not introduce it |
| `col-md-*` vs `col-sm-*` | **414 vs 100** | 295 vs 33 | Same desktop-first skew, larger absolute volume |
| `--space-*` / `--text-*` tokens | **none** | none ✅ | Confirmed gap — A1.1's job |
| `--color-*`, `--radius-*`, `--shadow-*`, `--font-*` | **present, light + dark** | present | Palette is good and **stays** |
| Locales × lang files | **3 × 34** (`ar`, `en`, `fr`) | — | No new i18n infrastructure needed |
| Test files | **181**, sqlite `:memory:` | — | Full suite too slow for a per-stop hook |
| `.claude/` scaffolding | **does not exist** | assumed buildable | Must be built before any wave — see PART 7 |
| `docs/design-system.md` | **does not exist** | created in A1 | ✅ still A1's deliverable |

**What actually reads as dated** (unchanged conclusion, stronger evidence): no type scale, no spacing
scale, 87 identical cards sharing one radius and one shadow, and 92 icons used inconsistently with no
canonical vocabulary. The palette is good and stays.

**The design system rebuild is still the bottleneck.** Phase A lands first.

---

# PART 1 — GLOBAL UI CONTRACT (prepend to every prompt)

```
=== SPIMS GLOBAL UI CONTRACT (v5) — applies to every line of UI you write ===

BEFORE WRITING MARKUP:
- Read /mnt/skills/public/frontend-design/SKILL.md.
- Read public/css/spims-theme.css and resources/views/components/*.
- Read docs/design-system.md (created in Phase A; authoritative over this block if they differ).

DESIGN SYSTEM — USE IT, DO NOT REINVENT IT:
- Consume tokens only. Never hard-code a colour, font, radius, shadow, spacing value, or font-size.
  Use --color-*, --font-*, --radius-*, --shadow-*, --space-*, --text-*.
- Reuse the Blade component library before writing raw markup. If a component is missing, ADD it to
  the library with the same API shape as its siblings — never inline a one-off.
- Forbidden: `card border-0 shadow-sm` (use <x-card>), bare <h1> (use <x-page-header>), `text-muted`,
  `bg-light`, `bg-secondary` (use tokens), inline style="" for anything themeable.
- Surface hierarchy is expressed by VARYING treatment: a primary panel, a quiet secondary surface,
  and a bare listing are three different things. Do not give every block the same radius + shadow.
- Icons: Bootstrap Icons, used deliberately for scanability — one per nav item, status, empty state,
  and primary action group. Icon + text for anything destructive or ambiguous; never icon alone.
  There are already 92 icon instances in the repo: your job is to make them CONSISTENT with the
  vocabulary in docs/design-system.md, not to add more.

RESPONSIVE — MOBILE-FIRST, THREE BREAKPOINTS MUST BE VERIFIED:
- Mobile-first: unprefixed classes are the phone layout. A grid with only `col-md-*` is a bug.
- 360px, 768px, 1440px must ALL render with: no horizontal page scroll; no clipped or overlapping
  text; tap targets >=44x44px with >=8px between adjacent targets; forms single-column on phone.
- DATA TABLES: every table in .spims-table-wrap. Any table with >4 columns must ALSO have a mobile
  card fallback (<x-data-table> renders stacked labelled cards below md). A horizontally-scrolling
  9-column table on a phone is not acceptable.
- Multi-input inline form rows become a modal or stacked fieldset on phone.
- Money and figures stay legible at 360px; never wrap mid-number; tabular-nums for numeric columns.

CONTENT AND CORRECTNESS:
- NEVER print a raw enum ->value. Map through a lang key.
- NEVER print a *_minor integer. Use \App\Support\Money::fromMinor($minor, $currency)->format().
- Localize every string in lang/{ar,fr,en}/*.php — all THREE locales, every key, every time.
  Arabic is RTL-first: logical CSS properties only (margin-inline, padding-inline, inset-inline,
  text-align:start/end). Never left/right.
- Copy: active voice; a button says what happens ("Save changes", not "Submit"); an action keeps the
  same name through the whole flow; empty states invite an action; errors say what happened and how
  to fix it; sentence case, no ALL-CAPS labels.

QUALITY FLOOR (part of "done", every step):
light + dark + RTL correct · 360/768/1440 correct · visible keyboard focus · prefers-reduced-motion
respected · no new hard-coded English, raw enum, or raw minor units.

AUTHORIZATION (unchanged): protected pages keep permission: middleware + AuthorizeService; mutations
go through the existing service wrapped by AuditLogWriter::withAudit(). Guest pages are the only
public ones. A new offering-owned permission key MUST be registered in config/permission_scopes.php,
and a new offering-owned model in ResourceScopeResolver::offeringIdsFor().

DEFINITION OF DONE — you are NOT finished until:
  ./scripts/validate-step.sh <your-step-id>
exits 0. Do not declare completion on a red or unrun validation.
```

---

# PART 2 — PHASE A: DESIGN SYSTEM (the bottleneck, now decomposed)

⛔ **A1 runs alone.** No other UI step is in flight. But it is now **six substeps with six gates**,
so a wrong turn is caught at the substep, not after the whole thing is built.

### STEP A1.1 — Token layer
Add the two missing scales to `public/css/spims-theme.css`, **without touching the palette**:
- **Type scale**: `--text-xs … --text-4xl`, clamp-based and fluid 360→1440, each paired with a
  `--leading-*` line-height. Nothing in the portal currently has type hierarchy — this creates it.
- **Spacing scale**: `--space-0 … --space-24` on a consistent ratio.
- **Focus ring**: one `--focus-ring` token, used by every interactive element.
- **Motion**: `--motion-fast/base/slow` plus a global `prefers-reduced-motion` guard.
- Every new token defined for **both** light and dark.

> **GATE A1.1** — `tests/Feature/Design/DesignTokenTest.php`
> asserts: every `--text-*`, `--leading-*`, `--space-*`, `--focus-ring`, `--motion-*` token is
> present; each is defined in **both** the light and dark blocks; no token references a literal hex
> outside the palette block; a `prefers-reduced-motion` media query exists.

### STEP A1.2 — Surface tier system
Replace "one card for everything" with three documented tiers — **primary panel**, **quiet surface**,
**bare listing** — each with its own radius/shadow/border/background treatment. Ship as
`<x-card variant="panel|quiet|bare">`. Document *when each applies*.

> **GATE A1.2** — `tests/Feature/Design/SurfaceTierTest.php`
> asserts: all three variants render; each emits a **different** class set (a tier that renders
> identically to another is a failed tier); an unknown variant throws rather than silently
> falling back.

### STEP A1.3 — Correctness primitives
The components that make whole classes of bug structurally impossible. Build these **before** any
other component, because A2 and every later step depend on them:
- `<x-badge>` — resolves the lang key internally. Raw enum output becomes impossible.
- `<x-money>` — takes minor units + `Currency`, calls `Money::fromMinor()->format()`. Raw minor
  units become impossible.
- `<x-icon>` — single entry point for Bootstrap Icons, keyed by the vocabulary name, not the raw
  `bi-*` class.

> **GATE A1.3** — `tests/Feature/Design/CorrectnessPrimitiveTest.php`
> asserts: `<x-badge>` given an enum emits the **localized label in all three locales** and never the
> raw `->value`; `<x-money>` given `(123456, Currency::USD)` emits a formatted string and never the
> bare integer; `<x-money>` renders correctly under RTL; `<x-icon>` rejects an unknown vocabulary key.

### STEP A1.4 — Structural components
`<x-stat>`, `<x-field>`, `<x-modal>`, `<x-tabs>`, `<x-toolbar>`, `<x-avatar>`, `<x-progress>`,
`<x-timeline>`, `<x-file-drop>`, plus an upgraded `<x-page-header>` (keeping its current API so the
67 views already using it do not break) and mobile-first grid primitives.

> **GATE A1.4** — `tests/Feature/Design/ComponentLibraryTest.php`
> asserts: every component renders with minimum props and with full props; **`<x-page-header>`'s
> existing call signature still works** (regression guard for the 67 existing usages); every
> component exposes a visible focus state; no component emits `style="`.

### STEP A1.5 — `<x-data-table>`
The hardest component and the one the responsive mandate rests on. Desktop `<table>` **plus** a
stacked labelled-card fallback below `md`, from a single call site. Numeric columns get
`tabular-nums`.

> **GATE A1.5** — `tests/Feature/Design/DataTableTest.php`
> asserts: a single invocation emits **both** the table markup and the mobile card markup; column
> labels are repeated in the card fallback; a >4-column table without the fallback fails; numeric
> columns carry the tabular-nums class; renders under RTL.

### STEP A1.6 — Documentation, icon vocabulary, and route anchors
- **Icon vocabulary**: one canonical icon per domain concept, reconciling the **92 existing
  instances**. Where the repo already uses an icon consistently for a concept, adopt it rather than
  inventing a new one.
- **`docs/design-system.md`**: tokens, the three surface tiers and when each applies, every
  component's props + a copy-pasteable example, the icon vocabulary, the responsive rules, and a
  **migration table** mapping every legacy pattern to its replacement.
- **Route anchors**: add anchor comments to `routes/web.php` — one per track — so every later step
  appends at a known line and lanes stop colliding. (There are currently no track anchors, only four
  sparse `// #NN` feature tags.)

> **GATE A1.6** — `tests/Feature/Design/DesignDocsTest.php`
> asserts: `docs/design-system.md` exists and contains a section per component actually present in
> `resources/views/components/`; the migration table names **every** banned pattern from the guard
> hook; `routes/web.php` contains each declared track anchor exactly once.

> ### ⛔ HUMAN DESIGN REVIEW GATE — after A1.6, before W2
> The only gate an agent cannot stand in for. Render a scratch page at 360/768/1440 × light/dark/RTL
> and confirm the three surface tiers **actually look different**. ~30 minutes, once. If A1 is wrong,
> 21 downstream steps inherit it.

---

### STEP A2 — Migrate existing views · **9 lanes, rebalanced**

Presentation-only refactor: **no routes, no controllers, no schema.** Per view: apply the migration
table, eliminate raw enums and hard-coded English (adding keys to **all three** locales), mobile-first
grid pass, apply the icon vocabulary, verify 360/768/1440 × light/dark/RTL. Note IA problems in the
PR; do not fix them here.

**Why the lanes changed:** v4's seven lanes named 126 of 168 views. Thirty-eight were orphaned —
including every `auth/` view (login, register, password reset: the highest-traffic unauthenticated
surface in the product) and all three `errors/` pages. v4's A2-g also held 40 views against A2-b's 8.

The 12 views under `events/`, `surveys/`, `projects/` and `live-quiz/` are **excluded from A2** —
they are owned by Steps 9–12, which now migrate them as part of audit-then-complete. That leaves
**152 views across 9 lanes**.

| Lane | Scope | Views |
|---|---|---|
| **A2-a** ⛔ | `layouts/`, `partials/`, `hubs/`, `roles-hub/`, `dashboard.blade.php`, `home.blade.php` — **owns all shared shell files; must merge before the other lanes rebase** | 17 |
| **A2-b** | `auth/` (+`auth/partials/`), `errors/`, `catalog/` (+partials), `announcements/`, `notifications/`, `settings/` — *unauthenticated and brand-critical; review with extra care* | 17 |
| **A2-c** | `learn/` (+partials), `courses/`, `assessments/`, `assignments/`, `completion/`, `discussions/` (+partials), `attendance/`, `live/` | 16 |
| **A2-d** | `teach/**` (all subdirectories), `offerings/` (+partials) | 20 |
| **A2-e** | `finance/`, `credentials/`, `enrollments/`, `grades/`, `advising/`, `applications/` | 17 |
| **A2-f** | `admin/offerings/`, `admin/gradebook/`, `admin/assessments/`, `admin/assessment-templates/`, `admin/attendance/`, `admin/completion-criteria/`, `admin/offering-closing/` | 16 |
| **A2-g** | `admin/programs/`, `admin/courses/`, `admin/semesters/`, `admin/academic-years/`, `admin/grading-schemes/`, `admin/users/`, `admin/translations/`, `admin/theme/` | 17 |
| **A2-h** | `admin/applications/`, `admin/application-forms/`, `admin/enrollments/`, `admin/communications/`, `admin/credentials/`, `admin/certificate-templates/`, `admin/events/`, `staff/surveys/` | 16 |
| **A2-i** | `admin/reports/`, `admin/finance/`, `admin/live/`, `superadmin/` (+`superadmin/audit/`) | 16 |
| | **Total** | **152** |

**A2-a is not peer to the others.** It owns `layouts/` and `partials/` — files every other lane
renders inside. Merge A2-a first, then rebase the remaining eight onto it. Running all nine truly
concurrently means eight lanes building against a shell that is about to change underneath them.

> **GATE A2-\*** (per lane) — `./scripts/validate-step.sh A2-<x>` asserts:
> zero banned patterns remain **in that lane's files**; every `__()` / `@lang()` key added by the lane
> resolves in **`ar`, `en` and `fr`** (missing-key check, all three); no `col-md-*` without a
> corresponding unprefixed or `col-sm-*` class in touched files; the lane's views still render (route
> smoke test); `git diff --stat` shows **no** changes under `routes/`, `app/Http/Controllers/`, or
> `database/migrations/` — a presentation-only lane that touched a controller has escaped its scope.

### STEP A3 — Design QA harness · runs parallel with A2
- **`DesignSystemAdoptionTest`** — scans Blade files, fails on banned legacy patterns, with a
  **shrink-only allowlist**: store today's counts (87 raw cards, 72 bare `<h1>`, 414 `col-md-*`),
  fail if any grows. This is the guardrail that survives long after the pack is finished.
- **`ResponsiveContractTest`** — asserts tables use `<x-data-table>`, and no inline form row with
  more than 3 controls survives in a migrated file.
- **`LocaleParityTest`** — asserts `ar`, `en` and `fr` have identical key sets across all 34 lang
  files. (v4 had no equivalent; three-locale drift is the most likely silent regression in a
  9-lane parallel migration.)
- Add a human "Design review checklist" section to `docs/design-system.md`.

> **GATE A3** — the three tests exist, pass, and **fail correctly when fed a deliberate violation**
> (`validate-step.sh A3` injects a temp file containing `card border-0 shadow-sm` and asserts the
> suite goes red). A guard test that cannot fail is not a guard.

> 📌 **Pull A3 forward.** It is scheduled parallel with A2, but it is the portable guardrail that
> works in any editor and in CI forever. If anything slips, ship A3 **before** A2, not after.

---

# PART 3 — TRACK 1: Public & marketing

Prepend the GLOBAL UI CONTRACT + `This is new UI — build it natively in the new design system from
docs/design-system.md. No legacy patterns.`

### STEP 1 — Public program pages
`/programs`, `/programs/{code}`. Additive columns: `programs.description`,
`programs.marketing_summary`, `courses.description`.
Brochure page: hero, `<x-stat>` row (total credits, elective credits, max semesters, passing
threshold), per-`year_level` course sequence, fee summary via `<x-money>`, apply CTA **with an
admissions fallback so it is never dead**, print stylesheet.

> **GATE 1** — migration is additive and reversible; `/programs` and `/programs/{code}` return 200
> **as a guest**; a program with no open offering still renders a working CTA (no dead link); no raw
> minor units in output; all copy resolves in three locales.

### STEP 2 — Landing rebuild
Hero, program tier strip (hide empty tiers), featured courses, how it works, fees & payments, trust
strip with a **working** verify-a-credential widget, footer CTA. Pre-wire `Route::has('courses.show')`.
The hero should open with the most characteristic thing in this school's world — not a
big-number-plus-gradient default.

> **GATE 2** — guest 200; empty tiers genuinely hidden (test with zero programs in a tier); the
> verify widget resolves a **real seeded serial** and rejects a bogus one; no `Route::has` guard
> renders a dead link.

### STEP 3 — Programs-first catalog
Three `<x-tabs>` (Programs default / Courses / Standalone). **Consume the currently-dead `$programs`
variable.** Price on every card, "Part of:" program chips, filters for program type + mode,
deep-linkable.
Shared `partials/program-card` + `course-card`.

> **GATE 3** — `$programs` is actually rendered (assert a seeded program name appears); each tab is
> deep-linkable and restores from the URL; filters compose; empty filter result shows an
> `<x-empty-state>`, not a blank page.

### STEP 4 — Public course detail
`/courses/{code}`. Description, prerequisites (linked), parent programs with year level +
required/elective, open offerings with mode/dates/price/seats, instructors, guest sign-in prompt vs
authed actions.

> **GATE 4** — guest 200; a course with prerequisites links them and they resolve; a course in **two**
> programs lists both with correct year level; guest sees a sign-in prompt where an authed user sees
> an action.

### STEP 5 — Semester calendar (operator)
Year selector; per-semester timeline showing registration / add-drop / teaching regions with a today
marker (**pure CSS, RTL-mirrored**); status pills with a `DRAFT→OPEN→IN_PROGRESS→CLOSED` state machine
(audited, illegal transitions rejected); add-year / add-semester in `<x-modal>`.

> **GATE 5** — every legal transition writes an audit entry; **every** illegal transition is rejected
> (assert the full matrix, not one case); a view-only user sees no controls **and** gets 403 on POST;
> the today marker mirrors correctly under RTL.

---

### STEP 6 — Enforce the silent academic rules *(backend only)*

> ⚠️ **v5 correction.** v4 asked the agent to build five rules. **Two of them already exist.**
> `EnrollmentService::drop()` already enforces `add_drop_end_week`; `withdraw()` already enforces
> `last_withdrawal_week` and computes `withdrawal_refund_percent`. The lang keys
> `enrollment.add_drop_closed` and `enrollment.withdrawal_closed` are already present in all three
> locales. Building them again risks regressing working code.

```
STEP 6 — RULES TO ENFORCE in EnrollmentService.

READ app/Services/Enrollment/EnrollmentService.php FIRST. Already enforced, DO NOT REBUILD:
  - offering status Open; financial hold; advising hold
  - cohort registration window
  - course prerequisites (via AcademicRecord.is_passing)
  - active matriculation in a program containing the course
  - max_credits_per_semester and max_courses_per_semester
  - add/drop window on drop()            <- rule 2, ALREADY DONE
  - last_withdrawal_week on withdraw()   <- rule 3, ALREADY DONE

--- 6a. max_semesters_to_graduate — HARD BLOCK ---
The field is seeded and NEVER READ. Add the check to assertCanRegister().
Count distinct semesters in which the student has held an enrollment in this program; block when it
would exceed programs.max_semesters_to_graduate. Admin override via the existing
'enrollment.override' permission path. lang key: enrollment.max_semesters.

--- 6b. Admin override for drop/withdraw windows ---
The window checks exist but have no override path. Add the same $adminOverride parameter already
used by register() to drop() and withdraw(), authorized through 'enrollment.override' and audited as
'enrollment.override_drop' / 'enrollment.override_withdraw'.
Refund tier follows withdrawal_refund_percent only WITHIN the window; an override past the window
refunds 0 unless explicitly set.

--- 6c. year_level sequencing — WARN, NOT BLOCK, per-program flag ---
Additive nullable boolean `enforce_year_sequence` on `programs`, DEFAULT FALSE, alongside the
existing rule fields. (Confirmed absent from all migrations today.)
  - FALSE (default): if the student has not passed all REQUIRED program courses at a lower
    year_level, ALLOW the enrollment and emit a non-blocking warning.
    lang key: enrollment.sequence_warning.
  - TRUE: hard block (lang key enrollment.sequence_blocked) WITH an admin override path shipped in
    the SAME release. Never ship the block without the override.
  - RATIONALE (put this in the code comment and in the docs): real dependencies are already
    expressed and hard-enforced as course prerequisites. year_level is a curriculum PLAN, not a
    dependency. Blocking on it double-enforces something already expressed properly, and masks a
    diagnostic: repeated desire to block usually means a prerequisite is missing from the data.
  - INSTRUMENT THE WARNING so it is not decorative: every time it fires, write a structured
    AuditLog entry recording student, program, course and the year_level gap, and surface the
    warning on the student's degree-audit page and to the advisor.
    A warning nobody sees is the same failure mode as max_semesters_to_graduate being unread.

--- 6d. repeat-a-passed-course ---
Block by default (lang key enrollment.already_passed) unless an admin override is set.

Keep every check inside the existing service transaction. Add new keys to lang/{en,ar,fr}/enrollment.php
(the file already exists in all three locales — APPEND, do not rewrite).
```

> **GATE 6** — `tests/Feature/Enrollment/ProgramRuleEnforcementTest.php`, one test per rule **plus a
> happy path and a regression guard**:
> - `blocks_enrollment_past_max_semesters`
> - `allows_admin_override_past_max_semesters`
> - `blocks_self_drop_after_add_drop_week_but_allows_admin_override`
> - `blocks_self_withdraw_after_last_withdrawal_week_but_allows_admin_override`
> - `override_past_withdrawal_window_refunds_zero_by_default`
> - `warns_on_year_level_gap_when_flag_false_and_enrollment_still_succeeds`
> - `blocks_on_year_level_gap_when_flag_true_and_admin_override_bypasses`
> - `year_level_warning_is_recorded_and_surfaced` (assert the audit entry exists)
> - `blocks_re_enrollment_of_passed_course_without_override`
> - `normal_enrollment_still_succeeds` ← guards against over-blocking
> - **`existing_enrollment_engine_tests_still_pass`** ← run `tests/Feature/Enrollment/` in full;
>   this step edits a service with existing coverage, and the pre-existing rules must not regress.

### STEP 7 — Program rule-builder UI
Grouped fieldsets (Identity / Course-load / Time-to-graduation / Requirements / Credentialing),
inline help stating the **real** enforcement from Step 6, a live Alpine "rule preview" sentence
mirrored server-side on the show page, localized enum labels.
**Surface `enforce_year_sequence` as a labelled toggle** in Course-load or Requirements, help text:
*off* → students see a warning when taking courses out of the planned year order; *on* → the system
blocks it and an admin must override. Default off. The rule-preview sentence must state which mode
is active.

> **GATE 7** — the rule-preview sentence rendered client-side (Alpine) and server-side (show page) are
> **identical for the same program** (assert both); toggling `enforce_year_sequence` changes the
> sentence; every enum label resolves in three locales; a user without `programs.manage` gets 403 on
> POST.

---

# PART 4 — TRACK 2: API-to-web parity

Backend services and the mobile API already exist. **Do not build domain logic** — call the same
services the API controllers call.

> ⚠️ **v5 correction.** v4 stated Steps 9–12 have "zero student web UI". That is wrong. All four
> surfaces already have student routes in `routes/web.php` and views on disk — but thin:
> `events/` 4 views, `surveys/` 3, `projects/` 3, `live-quiz/` 2 (`live-quiz/join` is 17 lines).
> Every one of these steps therefore **opens with a gap audit** and completes what is missing rather
> than rebuilding what works. Each step also **owns the design-system migration of its own views**
> (they are excluded from A2).

**Substep shape shared by Steps 9–12:**
- **`.1 Audit`** — enumerate the surface's API endpoints and its existing web routes/views side by
  side. Produce a written gap list in the PR: *endpoint → has web equivalent? → what's missing?*
  No production code in this substep.
- **`.2 Complete`** — build only the gaps the audit found.
- **`.3 Migrate`** — bring the surface's views onto the new design system (the A2 treatment).

### STEP 9 — Student web UI for Events
Existing: `/events`, `/events/mine`, `/events/{event}`, reserve, cancel + 4 views.
Complete: capacity meter, seats-left, reserved state, check-in code, and whichever of the 7 API
endpoints the audit shows unreached.

> **GATE 9** — every endpoint in the audit list is marked *covered* or *explicitly deferred with a
> reason*; reserving a full event fails gracefully; a non-reserved student cannot see a check-in
> code; capacity meter is correct at 0%, 50%, 100%.

### STEP 10 — Student web UI for Surveys
Existing: `/surveys`, `/surveys/{survey}`, submit + 3 views.
Complete: **all** question types, the one-response rule, closed-window handling, and **genuine
anonymity** for anonymous surveys.

> **GATE 10** — every question type renders and round-trips a submission; a second submission is
> rejected; a closed survey rejects submission; for an anonymous survey, assert **no query path links
> response to user** — this is the highest-consequence assertion in the step.

### STEP 11 — Student web Live Quiz player
Existing: `/live-quiz/join`, `/live-quiz/sessions/{session}`, answer + 2 views (thin).
Complete: `/state` JSON endpoint polled ~2s by Alpine, `/results`, lobby → question → locked →
results. **All timing from a server-supplied deadline + server clock, never the client clock.**
Graceful host-ended state. Largest tap targets in the portal.

> **GATE 11** — assert the client **never** computes a deadline from its own clock (the state payload
> carries server time and deadline); an answer submitted after the server deadline is rejected; a
> host-ended session shows the ended state rather than erroring; tap targets ≥44px at 360px.

### STEP 12 — Student web Projects & peer evaluation
Existing: `/projects`, `/projects/mine`, `/projects/{p}` + 3 views.
Complete: deliverable submit as a **real upload via StorageService** (not a URL textarea), peer-eval
with lock after submission, non-member 403.

> **GATE 12** — a non-member gets 403 on every project route; a peer evaluation cannot be edited after
> submission; upload goes through StorageService (assert the file lands in storage, not that a URL
> string was saved); file-type and size limits enforced.

### STEP 13 — Instructor submission grading workbench ⭐
*Highest instructor value in the pack.* Today the only path for online work is a
`student_id,score,feedback` CSV textarea. **Decomposed into three substeps** — this is the largest
single build in the pack.

- **13.1 — Submissions roster.** Status, version, score, filters, "Grade next ungraded".
  > **Gate:** roster paginates; every filter composes; "Grade next ungraded" skips graded submissions
  > and terminates cleanly when none remain.
- **13.2 — Single-submission view + grading.** Content + files + version history (left), grading
  panel (right), **one column on phone**. Grading goes through `AssignmentService`.
  > **Gate:** grading writes through `AssignmentService` and is audited; version history shows all
  > versions; layout is single-column at 360px; a grader without permission gets 403.
- **13.3 — AI-suggest.** Surface the currently-unused `EssayAiGrader` as an editable, clearly-labelled
  suggestion that is **never auto-applied**.
  > **Gate:** the suggestion is never persisted without an explicit instructor action (assert no write
  > occurs on suggest); the UI labels it as AI-generated; grader edits to the suggestion are what get
  > saved; a grader can dismiss it entirely and grade manually.

### STEP 14 — Gradebook cell entry + proctor review polish
Make the read-only gradebook editable per component (validated, audited, blocked when locked with a
banner, percent/letter recompute). Rewrite the proctor review view — currently hard-coded English
("terminated for cheating", "Proctor events", "type", "at") with raw enums — into a localized
severity timeline.

> **GATE 14** — a locked gradebook rejects the write **and** shows the banner; every cell edit is
> audited; percent and letter recompute correctly against the offering's grading scheme; the proctor
> view has **zero** hard-coded English and zero raw enums in three locales.

### STEP 15 — Parity sweep + guardrail ⛔ *last*
- **`RawEnumAndMinorUnitsGuardTest`** — repo-wide.
- **`docs/api-web-parity-matrix.md`** — service / student API / student web / instructor web / admin
  web, per domain. Seed it from the Step 9–12 audit lists, which already contain most of this.
- **CLAUDE.md rule:** *no new student or instructor API endpoint merges without shipping or explicitly
  deferring its web equivalent in the matrix.*

> **GATE 15** — the guard test fails when fed a deliberate raw-enum and a raw-minor-unit violation;
> the matrix has **no blank cells** (every cell is a link or an explicit "deferred: reason"); the
> CLAUDE.md rule is present.

---

# PART 5 — TRACK 3: Demo & QA

### STEP 0 — QA harness
`PublicSurfacesSmokeTest`; enumerate missing surfaces. **No production code.**

> **GATE 0** — the test enumerates every route in `routes/web.php` and asserts each returns a non-5xx
> for an appropriately-permissioned actor; the output is a written list of surfaces that 404 or 500
> **committed to the repo** as the baseline the rest of the pack works against.

### STEP 8A — Demo dataset + `spims:demo-reset`
`DemoDataSeeder` **already exists** (16 accounts, password `Spims@Test2026!`) — extend it, do not
replace it. **Decomposed:**

- **8A.1 — Content & catalog.** Program/course descriptions (needs Step 1's columns); a fully-built
  walkthrough offering for `student1@spims.test`: every content-item type, open assessment,
  discussion thread, future live session, released grades.
- **8A.2 — Finance & lifecycle.** Paid + open invoice, wallet across all buckets; an application in
  every status; semesters in CLOSED / IN_PROGRESS / DRAFT; issued credentials with a printed, valid
  verify serial.
- **8A.3 — Parity surfaces & rule branches.** Seed an **event, a survey, a live quiz and a team
  project** so Steps 9–12 have something to render. Seed **one program with
  `enforce_year_sequence = true`** so both branches of Step 6c are demonstrable.
- **8A.4 — `spims:demo-reset` command.** Idempotent re-seed.

> **GATE 8A** — `DemoDataSeederTest` (already exists — extend it) asserts every seeded fixture is
> reachable; `spims:demo-reset` run **twice in a row** leaves identical state (idempotency); the
> printed verify serial actually validates through the credential verifier; both
> `enforce_year_sequence` branches have a seeded program.

### STEP 8B — Demo mode
`SPIMS_DEMO_MODE` flag; dismissible banner with role quick-login (`/demo/login`, rate-limited,
**403 when the flag is off**, audited); `/demo/guide` per-role checklist deep-linking into each flow.
Fails closed in production.

> **GATE 8B** — with the flag **off**, `/demo/login` returns 403 and the banner does not render
> (assert both); with `APP_ENV=production` the flag cannot be enabled at all; quick-login is rate
> limited (assert the limit trips); every quick-login writes an audit entry; every `/demo/guide` deep
> link resolves to a live route.

---

# PART 6 — EXECUTION ORDER & VALIDATION HARNESS

## 6.1 Wave order

```
W-1  scaffolding ──> W0 ──┐
                          ├──> A1.1…A1.6 (BOTTLENECK) ──> ⛔HUMAN GATE──> A2-a ──> A2-b…A2-i
                          │              └──> A3 (pull forward)
              STEP 6 ─────┘ (backend-only; runs during A1)
```

| Wave | Steps | Parallel | Notes |
|---|---|---|---|
| **W-1** | `.claude/` scaffolding + `scripts/` | 1 | **New in v5.** Nothing exists today. Build and *prove* the hooks before any step runs. |
| **W0** | 0, 6 | 2 | Neither writes UI → safe during A1. Step 6 is the highest value per line in the pack. |
| **W1** | **A1.1 → A1.6** | ⛔ 1, alone | Six sequential substeps, six gates. Blocks everything after. |
| **⛔** | **HUMAN DESIGN REVIEW** | — | ~30 min. The one gate no agent can stand in for. |
| **W1.5** | **A3** | 1 | **Pulled forward from v4.** The guard tests protect all of W2. |
| **W2a** | **A2-a** | ⛔ 1, alone | Owns `layouts/` + `partials/`. Merge before W2b starts. |
| **W2b** | A2-b … A2-i | 🔀 8 | Rebase onto merged A2-a first. Run as 2 batches of 4 if the machine struggles. |
| **W3** | 1, 5, 9, 10, 12, 13, 14 | 🔀 7 | All independent. Step 13 is three substeps inside one lane. |
| **W4** | 2, 3, 11, 7, 8A | 🔀 5 | 2 and 3 need 1; 7 needs 6; 8A needs 1's columns. |
| **W5** | 4, 8B | 2 | 4 needs 3; 8B needs 8A. |
| **W6** | **15** | ⛔ 1, last | The matrix is only truthful after 9–14. |

**Contention:** `lang/*` is the highest merge-conflict surface — 34 files × 3 locales, and nine A2
lanes all appending. Give each step its **own** lang file where possible and only ever **append** to
shared ones. `routes/web.php` gets track anchor comments in **A1.6** so each step appends at a known
place. After A1, later steps add only component-scoped CSS at file end, never the token layer.

**Single agent, serial order:**
`scaffolding → 6 → A1.1…A1.6 → A3 → A2-a → 13 → A2-d → 11 → 1 → 2 → A2-b → A2-c → A2-e … → 15`

## 6.2 The validation harness *(new in v5)*

Every step declares its gate in one place, and one script runs it. No step is "done" on an agent's
say-so.

**`prompts/steps.tsv`** — the single source of truth mapping step → test filter → scope glob:

```tsv
# step	test-filter	scope-glob
A1.1	DesignTokenTest	public/css/spims-theme.css
A1.2	SurfaceTierTest	resources/views/components
A1.3	CorrectnessPrimitiveTest	resources/views/components
A1.4	ComponentLibraryTest	resources/views/components
A1.5	DataTableTest	resources/views/components
A1.6	DesignDocsTest	docs/design-system.md,routes/web.php
A3	DesignSystemAdoptionTest|ResponsiveContractTest|LocaleParityTest	tests/Feature/Design
A2-a	Portal|Smoke	resources/views/layouts,resources/views/partials,resources/views/hubs
A2-b	Auth|Portal	resources/views/auth,resources/views/errors,resources/views/catalog
6	ProgramRuleEnforcementTest|Enrollment	app/Services/Enrollment
13	Assessment|StaffUi	app/Http/Controllers/Teach
# … one row per step
```

**`scripts/validate-step.sh`** — the per-step gate the `Stop` hook and the agent both call:

```bash
#!/usr/bin/env bash
# ./scripts/validate-step.sh A2-b
set -uo pipefail
step="${1:?usage: validate-step.sh <step-id>}"
row=$(grep -P "^${step}\t" prompts/steps.tsv) || { echo "unknown step: $step" >&2; exit 1; }
filter=$(cut -f2 <<<"$row"); scope=$(cut -f3 <<<"$row")
fail=0

echo "== [$step] scoped tests: $filter"
php artisan test --filter="$filter" || fail=1

echo "== [$step] banned patterns in scope"
IFS=',' read -ra dirs <<<"$scope"
for d in "${dirs[@]}"; do
  [[ -e "$d" ]] || continue
  grep -rnE 'card border-0 shadow-sm|class="[^"]*\btext-muted\b|->value\s*\}\}|(total|amount|price|balance)_minor\s*\}\}' \
    "$d" --include='*.blade.php' && { echo "!! banned pattern above" >&2; fail=1; }
done

echo "== [$step] three-locale parity"
php artisan test --filter=LocaleParityTest || fail=1

echo "== [$step] scope escape check"
if [[ "$step" == A2-* ]] && git diff --name-only main... | grep -qE '^(routes/|app/Http/Controllers/|database/migrations/)'; then
  echo "!! A2 lane touched routes/controllers/migrations — presentation-only violated" >&2; fail=1
fi

[[ $fail -eq 0 ]] && echo "== [$step] PASS" || echo "== [$step] FAIL" >&2
exit $fail
```

**`scripts/validate-wave.sh`** — the merge gate. **This is where the full suite runs**, once per wave,
not once per agent stop:

```bash
#!/usr/bin/env bash
# ./scripts/validate-wave.sh W2b
set -uo pipefail
wave="${1:?usage: validate-wave.sh <wave>}"
source scripts/waves.sh   # defines steps_for(<wave>)
fail=0

for s in $(steps_for "$wave"); do
  echo "=== validating lane $s ==="
  git checkout "worktree-$s" -- . 2>/dev/null || true
  ./scripts/validate-step.sh "$s" || fail=1
done

echo "=== FULL SUITE (wave gate) ==="
php artisan test || fail=1

echo "=== adoption counters must not grow ==="
php artisan test --filter=DesignSystemAdoptionTest || fail=1

echo "=== lanes must merge cleanly against each other ==="
for s in $(steps_for "$wave"); do
  git merge --no-commit --no-ff "worktree-$s" >/dev/null 2>&1 \
    || { echo "!! $s conflicts" >&2; fail=1; }
  git merge --abort 2>/dev/null || true
done

[[ $fail -eq 0 ]] && echo "=== $wave PASS — safe to merge ===" \
                  || { echo "=== $wave FAIL — DO NOT MERGE ===" >&2; exit 1; }
```

---

# PART 7 — RUNNING THIS PACK WITH MINIMAL SUPERVISION

## 7.0 What genuinely cannot be automated

**Fully hands-off A-to-Z is not achievable, and chasing it costs more than it saves.** Two gates need
a human. Everything else runs unattended.

| Gate | Why a human | Cost |
|---|---|---|
| **A1 design review** | An agent cannot judge "does this look good." A1 defines the visual language 21 downstream steps inherit. | ~30 min, once |
| **Merge review per wave** | Agents write green tests around their own assumptions. Someone reads the diff. `validate-wave.sh` makes this a *review*, not a *hunt*. | ~15 min per wave |
| ~~Registrar year_level decision~~ | **Resolved** — warn + per-program flag, defaults off. Folded into Step 6c. | — |

Target ~85% autonomy: agents run the waves, you clear two gate types and merge.

## 7.1 W-1 — Build the scaffolding first

**None of this exists in the repo today.** It is Wave −1 for a reason: the hooks are the enforcement,
and a wave run without them is a wave run without guardrails.

```
.claude/
  settings.json          # hooks + permissions (committed, shared)
  agents/
    spims-ui.md          # subagent def for UI steps (isolation: worktree)
    spims-backend.md     # subagent def for backend steps
  hooks/
    guard-design.sh      # PreToolUse: block banned patterns at write time
    verify-step.sh       # Stop: refuse to finish on a failing SCOPED validation
    setup-worktree.sh    # WorktreeCreate: composer install, .env, migrate
  commands/
    run-step.md          # /run-step A2-c
prompts/
  GLOBAL-CONTRACT.md     # Part 1, included by every step prompt
  steps.tsv              # step -> test filter -> scope glob  (NEW in v5)
  step-A1.1.md … step-15.md
scripts/
  run-wave.sh
  waves.sh               # steps_for()  (NEW in v5)
  validate-step.sh       # (NEW in v5)
  validate-wave.sh       # (NEW in v5)
.worktreeinclude
```

**`.worktreeinclude`** — a worktree is a fresh checkout with no `.env`:
```
.env
.env.testing
```

**CLAUDE.md addition** (agents read this automatically):
```md
## Working this repo with agents
- Every UI change follows prompts/GLOBAL-CONTRACT.md. Read docs/design-system.md before any markup.
- Never edit layouts/, partials/, or components/ unless your step's scope says you own them.
- Append routes at the anchor comment for your track; never reorder routes/web.php.
- Add new lang keys to your step's own lang file; only ever append to shared ones.
  Every key goes into ALL THREE locales (ar, en, fr) — LocaleParityTest enforces this.
- A step is not done until ./scripts/validate-step.sh <step-id> exits 0.
```

## 7.2 Guardrails: hooks are the real enforcement

Prompts are advisory; hooks are not.

**Critical mechanics** (these bite people):
- **Exit code 2 blocks. Exit code 1 does not** — exit 1 is a non-blocking error and the action
  proceeds. Policy hooks must `exit 2`.
- `${CLAUDE_PROJECT_DIR}` stays at the **main checkout** even after Claude enters a worktree; the
  worktree path arrives as the `cwd` field in the hook's stdin JSON. Read `cwd` when you need the
  worktree.
- A mistyped hook path exits ~127 and is treated as non-blocking — your gate is silently off. Watch
  the first run for a `Failed with non-blocking status code` notice.
- Hooks need `jq` on `PATH`.

**`.claude/settings.json`:**
```json
{
  "hooks": {
    "PreToolUse": [
      { "matcher": "Edit|Write",
        "hooks": [ { "type": "command",
                     "command": "${CLAUDE_PROJECT_DIR}/.claude/hooks/guard-design.sh" } ] }
    ],
    "Stop": [
      { "hooks": [ { "type": "command",
                     "command": "${CLAUDE_PROJECT_DIR}/.claude/hooks/verify-step.sh",
                     "timeout": 900 } ] }
    ],
    "WorktreeCreate": [
      { "hooks": [ { "type": "command",
                     "command": "${CLAUDE_PROJECT_DIR}/.claude/hooks/setup-worktree.sh" } ] }
    ]
  }
}
```

**`guard-design.sh`** — blocks banned patterns *as they are written*:
```bash
#!/usr/bin/env bash
set -euo pipefail
input=$(cat)
path=$(jq -r '.tool_input.file_path // ""' <<<"$input")
content=$(jq -r '.tool_input.content // .tool_input.new_string // ""' <<<"$input")
[[ "$path" != *.blade.php ]] && exit 0

deny() {
  jq -n --arg r "$1" '{hookSpecificOutput:{hookEventName:"PreToolUse",
    permissionDecision:"deny", permissionDecisionReason:$r}}'
  exit 0
}

grep -q 'card border-0 shadow-sm' <<<"$content" && \
  deny "Banned: 'card border-0 shadow-sm'. Use <x-card variant=...> per docs/design-system.md."
grep -qE 'class="[^"]*\btext-muted\b' <<<"$content" && \
  deny "Banned: text-muted. Use the theme token class."
grep -qE '\->value\s*\}\}' <<<"$content" && \
  deny "Banned: raw enum ->value in output. Map through a lang key or use <x-badge>."
grep -qE '(total|amount|price|balance)_minor\s*\}\}' <<<"$content" && \
  deny "Banned: raw minor units. Use <x-money> or Money::fromMinor(...)->format()."
exit 0
```

**`verify-step.sh`** — ⚠️ **changed in v5.** v4 ran the **full** suite on every stop. With **181 test
files** and up to 8 concurrent agents, that is minutes of wall clock per stop, multiplied by every
agent, every time. It now runs the step's **scoped** validation; the full suite runs once per wave in
`validate-wave.sh`.

```bash
#!/usr/bin/env bash
set -uo pipefail
input=$(cat)
cwd=$(jq -r '.cwd' <<<"$input")     # follows Claude into the worktree
cd "$cwd" || exit 0

step=$(cat .claude/current-step 2>/dev/null || echo "")
if [[ -z "$step" ]]; then
  echo "No .claude/current-step — write your step id there before finishing." >&2
  exit 2
fi

if ! out=$(./scripts/validate-step.sh "$step" 2>&1); then
  echo "Step validation failed for [$step]. Fix before finishing:" >&2
  tail -60 <<<"$out" >&2
  exit 2
fi
exit 0
```

**`setup-worktree.sh`** — a fresh worktree has no `vendor/` or DB. This hook **replaces** the default
git behaviour and must print the absolute worktree path on stdout (everything else to stderr):
```bash
#!/usr/bin/env bash
set -euo pipefail
input=$(cat); name=$(jq -r '.name' <<<"$input")
repo="$CLAUDE_PROJECT_DIR"; wt="$repo/.claude/worktrees/$name"
git -C "$repo" worktree add "$wt" -b "worktree-$name" >&2
cd "$wt"
cp "$repo/.env" .env 2>/dev/null || true
composer install --no-interaction >&2
php artisan key:generate >&2
touch database/database.sqlite
php artisan migrate --seed >&2
echo "$name" > .claude/current-step 2>/dev/null || true
echo "$wt"        # <- the only stdout
```

Add `.claude/worktrees/` to `.gitignore`.

## 7.3 Parallelism: worktrees + headless, not agent teams

**Use git worktrees.** Each agent gets an isolated checkout on its own branch; Claude Code blocks
edits that reach back into the main checkout, so lanes physically cannot collide.

```bash
claude --worktree a2-c      # → .claude/worktrees/a2-c, branch worktree-a2-c
```

**Do not plan the automation around agent teams.** They are experimental, off by default
(`CLAUDE_CODE_EXPERIMENTAL_AGENT_TEAMS=1`), and — decisively — **teammates do not spawn in headless
`-p` mode**, which is exactly the mode a wave runner uses. Agent teams are worth trying
*interactively* for A1 (a lead plus a critic teammate is a genuinely good fit for design review), but
the wave runner should use plain parallel `-p` processes in worktrees.

Two gotchas: `-p` runs **skip the workspace-trust check**, so run `claude` once in the repo
interactively first; and `-p` runs have no exit prompt, so **they do not clean up their worktrees** —
the runner removes them explicitly.

## 7.4 The wave runner

```bash
#!/usr/bin/env bash
# scripts/run-wave.sh W2b  → runs every lane in the wave in parallel, one worktree each
set -uo pipefail
wave="$1"
source scripts/waves.sh
steps=($(steps_for "$wave")) || { echo "unknown wave"; exit 1; }

mkdir -p logs
for s in "${steps[@]}"; do
  (
    prompt="$(cat prompts/GLOBAL-CONTRACT.md prompts/step-${s}.md)"
    claude -p "$prompt" \
      --worktree "$s" \
      --permission-mode acceptEdits \
      --fallback-model sonnet \
      > "logs/${s}.log" 2>&1
    echo "[$s] exit=$?" >> logs/_wave.log
  ) &
done
wait
echo "=== $wave agents complete ==="; cat logs/_wave.log

# v5: the wave is NOT done until the gate passes.
./scripts/validate-wave.sh "$wave" || {
  echo "!! $wave failed validation — review logs/ before merging"; exit 1; }

for s in "${steps[@]}"; do
  echo "--- $s ---"; git log --oneline main.."worktree-$s" 2>/dev/null | head
done
```

**`scripts/waves.sh`:**
```bash
steps_for() {
  case "$1" in
    W0)   echo "0 6" ;;
    W1)   echo "A1.1 A1.2 A1.3 A1.4 A1.5 A1.6" ;;   # sequential, one agent
    W1.5) echo "A3" ;;
    W2a)  echo "A2-a" ;;
    W2b)  echo "A2-b A2-c A2-d A2-e A2-f A2-g A2-h A2-i" ;;
    W3)   echo "1 5 9 10 12 13 14" ;;
    W4)   echo "2 3 11 7 8A" ;;
    W5)   echo "4 8B" ;;
    W6)   echo "15" ;;
    *) return 1 ;;
  esac
}
```

Cleanup after merging a lane (`-p` runs do not self-clean; unlock first if git refuses):
```bash
git worktree unlock ".claude/worktrees/$s" 2>/dev/null || true
git worktree remove ".claude/worktrees/$s" --force
```

**Practical limits:** 3–5 concurrent agents is the sweet spot; the ceiling is CPU/RAM and rate limits,
not the tool. Run W2b's 8 lanes as two batches of 4 if the machine struggles.

## 7.5 Permission modes — how much rope

| Mode | Use for | Risk |
|---|---|---|
| `acceptEdits` | **Recommended default** for wave runs — file edits auto-approved, other tools still gated | Low |
| `auto` | Longer unattended runs; a classifier decides | Medium |
| `--dangerously-skip-permissions` | **Don't.** Combined with `-p` and worktrees the marginal gain is small and the blast radius is the whole machine | High |

The hooks in 7.2 are the real safety net and they run in **every** mode, including inside subagents.

## 7.6 Cursor

Cursor has no equivalent of hooks, worktree isolation, or headless waves.

1. **Recommended: Claude Code for the pack, Cursor for review.** Run the waves headless, then open the
   merged branches in Cursor to read diffs and hand-tune.
2. **Cursor-only fallback:** paste one step prompt per session, run strictly serially, and replace the
   hook layer with the test suite — make `DesignSystemAdoptionTest`, `LocaleParityTest` and
   `RawEnumAndMinorUnitsGuardTest` part of a **pre-commit hook**. Strictly worse, but it holds the line.

Either way, **A3 is pulled forward to right after A1** in v5 for exactly this reason: it is the
portable guardrail that works in any editor, in CI, and forever after this pack is finished.

## 7.7 Recommended first day

```
1.  W-1: build .claude/ + prompts/ + scripts/ (30–45 min)
2.  Prove the hooks: /hooks, then make a DELIBERATE violation and confirm it is blocked.
    A guard you have not seen block something is a guard you do not have.
3.  Prove the harness: ./scripts/validate-step.sh 6   (should fail — step 6 is not written yet)
4.  ./scripts/run-wave.sh W0        # Step 0 + Step 6, no UI, low risk
5.  Review + merge
6.  ./scripts/run-wave.sh W1        # A1.1 … A1.6, one agent, six gates
7.  ⛔ DESIGN REVIEW GATE — read docs/design-system.md, render a scratch page at
    360/768/1440 × light/dark/RTL, confirm the three surface tiers actually look different
8.  ./scripts/run-wave.sh W1.5      # A3 — the guard tests that protect all of W2
9.  ./scripts/run-wave.sh W2a       # A2-a alone (owns the shell); merge before continuing
10. ./scripts/run-wave.sh W2b … through W6, clearing the wave gate between each
```

If W0 does not come out clean, **fix the harness before running anything else.** The whole value of
this setup is that a bad step fails loudly in its own worktree instead of quietly on main.

---

# APPENDIX — v4 → v5 correction log

| # | v4 said | Reality | v5 does |
|---|---|---|---|
| 1 | 54 raw cards | **87 across 57 files** | Re-sized A2; adoption test seeded with real counts |
| 2 | 118 views | **168** (164 + 4 components) | Re-sized every A2 lane |
| 3 | 34/118 use `x-page-header` | **67** | A1.4 must preserve its API as a regression guard |
| 4 | 18 icon instances | **92** | Icon vocabulary *unifies existing* use, not introduces it |
| 5 | 295 / 33 grid classes | **414 / 100** | Same conclusion, larger volume |
| 6 | 7 A2 lanes | **covered only 126 of 168 views** | **9 lanes**, zero orphans; `auth/` + `errors/` given a home |
| 7 | A2-g = 40 views, A2-b = 8 | 5× imbalance | Lanes rebalanced to **16–20 each** |
| 8 | A2 lanes are peers | A2-a owns `layouts/` + `partials/` | **A2-a split into its own wave (W2a)**, merged before the rest |
| 9 | Steps 9–12 have "zero student web UI" | **All four have routes + views** (thin) | Re-scoped to **audit → complete → migrate** |
| 10 | Step 6 rules 2 & 3 to be built | **Already implemented**, lang keys present | Rewritten to target 6a/6b/6c/6d only; regression guard added |
| 11 | `DemoDataSeeder` to be extended | Exists ✅ | Confirmed; 8A decomposed into 4 substeps |
| 12 | A1 = one step | ~16 components + tokens + docs | **A1.1 … A1.6**, six gates |
| 13 | Step 13 = one step | Largest build in the pack | **13.1 / 13.2 / 13.3** |
| 14 | `Stop` hook runs full suite | **181 test files**, 8 concurrent agents | Scoped per step; full suite at the **wave** gate |
| 15 | A3 parallel with A2 | Guard tests protect A2 | **Pulled forward to W1.5**, before A2 |
| 16 | No validation harness | — | `validate-step.sh`, `validate-wave.sh`, `steps.tsv`, per-step gates |
| 17 | No three-locale enforcement | 3 × 34 lang files, 9 parallel lanes | **`LocaleParityTest`** added to A3 and to every step gate |
| 18 | `.claude/` assumed | **Does not exist** | Promoted to **Wave −1** with a "prove the hook blocks" step |
