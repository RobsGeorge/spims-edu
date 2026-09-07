# Step 6 — Enforce Silent Academic Rules (backend only)

Step ID: `6`  
Wave: W0  
Scope: app/Services/Enrollment, tests/Feature/Enrollment  
**Backend only — no UI, no migrations (except 6c's additive column).**

## CRITICAL: Read the existing service first

Open `app/Services/Enrollment/EnrollmentService.php` before writing anything.

**ALREADY IMPLEMENTED — do NOT rebuild these:**
- Offering status Open check
- Financial hold and advising hold checks
- Cohort registration window (add_drop_end_week) on register
- Course prerequisites via AcademicRecord.is_passing
- Active matriculation check
- max_credits_per_semester and max_courses_per_semester caps
- `drop()`: enforces add_drop_end_week — lang key `enrollment.add_drop_closed` EXISTS in all 3 locales
- `withdraw()`: enforces last_withdrawal_week, computes withdrawal_refund_percent — lang key `enrollment.withdrawal_closed` EXISTS

**Run `tests/Feature/Enrollment/EnrollmentEngineTest.php` first** and confirm it is green.
The pre-existing rules must stay green throughout this step.

## Rules to add

### 6a — max_semesters_to_graduate (HARD BLOCK)
- Field `programs.max_semesters_to_graduate` is seeded but never read.
- In `assertCanRegister()`: count distinct semesters in which this student has held an enrollment
  in this program; block if count would exceed `programs.max_semesters_to_graduate`.
- Admin override via the existing `enrollment.override` permission (already used by register()).
- Lang key: `enrollment.max_semesters` — add to lang/{en,ar,fr}/enrollment.php (APPEND only).

### 6b — Admin override for drop/withdraw windows
- `drop()` and `withdraw()` have no override path today.
- Add `bool $adminOverride = false` parameter (same pattern as register()'s existing $adminOverride).
- Gate on `enrollment.override` permission; audit as `enrollment.override_drop` / `enrollment.override_withdraw`.
- An override past the withdrawal window refunds 0% unless explicitly set — do not apply the
  withdrawal_refund_percent on an out-of-window admin override.

### 6c — year_level sequencing (WARN, per-program flag)
- Additive migration: nullable boolean `enforce_year_sequence` on `programs`, DEFAULT FALSE.
  Place it alongside the existing rule fields (max_credits_per_semester etc.). Confirmed absent today.
- When FALSE (default): if the student has not passed all REQUIRED program courses at a lower
  year_level, ALLOW the enrollment and emit a non-blocking warning.
  Lang key: `enrollment.sequence_warning`.
- When TRUE: hard block. Lang key: `enrollment.sequence_blocked`. Admin override MUST ship in the
  same release as the block — never ship the block without the override.
- INSTRUMENT the warning: every time it fires, write a structured AuditLog entry recording
  (student_id, program_id, course_id, attempted_year_level, highest_incomplete_year_level).
  Surface the warning on the student's degree-audit page and to the advisor.
- Code comment and docs note: year_level is a curriculum PLAN, not a dependency. Real dependencies
  are expressed as course prerequisites. Blocking on year_level double-enforces something already
  handled, and masks the real fix (adding the missing prerequisite).

### 6d — Repeat a passed course (BLOCK)
- Block by default (lang key: `enrollment.already_passed`) unless an admin override is set.
- Check: student has an enrollment for this course with `is_passing = true`.

## Keep every check inside the existing service transaction.

## Test file
`tests/Feature/Enrollment/ProgramRuleEnforcementTest.php` — one test per rule:
- `blocks_enrollment_past_max_semesters`
- `allows_admin_override_past_max_semesters`
- `blocks_self_drop_after_add_drop_week_but_allows_admin_override`
- `blocks_self_withdraw_after_last_withdrawal_week_but_allows_admin_override`
- `override_past_withdrawal_window_refunds_zero_by_default`
- `warns_on_year_level_gap_when_flag_false_and_enrollment_still_succeeds`
- `blocks_on_year_level_gap_when_flag_true_and_admin_override_bypasses`
- `year_level_warning_is_recorded_and_surfaced`
- `blocks_re_enrollment_of_passed_course_without_override`
- `normal_enrollment_still_succeeds`
- `existing_enrollment_engine_tests_still_pass` (run EnrollmentEngineTest in full)

## Done
Write `echo "6" > .claude/current-step` then run `./scripts/validate-step.sh 6`.
