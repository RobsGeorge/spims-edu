# Step A3 — Design QA Harness

Step ID: `A3`  
Wave: W1.5 (runs before A2, not parallel with it)  
Scope: tests/Feature/Design  
Note: This is the portable guardrail. It must be green before any A2 lane starts.

## What to build

### `DesignSystemAdoptionTest`
- Scans ALL Blade files (`resources/views/**/*.blade.php`)
- Counts occurrences of each banned pattern
- Reads a baseline count from `tests/Feature/Design/adoption-baseline.json`
- **Fails if any count GROWS above the baseline** (a regression)
- Passes if counts are equal or lower (migration progress)

Initial baseline (measured from the real repo — use these exact numbers):
```json
{
  "card border-0 shadow-sm": 87,
  "text-muted": null,
  "bg-light": null,
  "bg-secondary": null,
  "bare_h1": 72,
  "col_md_without_unprefixed": null
}
```
For the `null` entries: scan the repo and set the real count as the baseline. A null means
"measure and set" on first run, not "skip".

### `ResponsiveContractTest`
- Asserts every `<table>` in a migrated Blade file is inside `.spims-table-wrap`
  (once a file has been migrated by A2, it must pass this check)
- Asserts no inline form row with more than 3 adjacent `<input>` / `<select>` / `<textarea>`
  elements survives in a migrated file
- "Migrated" = the file was touched in the A2 branch (use `git diff --name-only`)

### `LocaleParityTest`
- For every file in `lang/en/`, assert the same file exists in `lang/ar/` and `lang/fr/`
- For every key in every `lang/en/*.php` file, assert the same key exists in the ar and fr versions
- A missing locale file is a failure. A missing key within a file is a failure.
- There are currently 34 lang files × 3 locales — all 34 must be present and key-complete.

### Self-test (verify the guard tests can actually fail)
Each test must be able to fail when fed a deliberate violation.
Add to each test a `@test` method prefixed `it_fails_when_`:
- `DesignSystemAdoptionTest::it_fails_when_banned_pattern_injected` — injects a temp file with
  `card border-0 shadow-sm` and asserts the scan would report a count increase
- `ResponsiveContractTest::it_fails_when_unwrapped_table_present` — asserts a bare `<table>`
  without `.spims-table-wrap` would fail
- `LocaleParityTest::it_fails_when_key_missing_from_locale` — asserts a missing key is detected

A guard test that cannot demonstrate its own failure is not a guard.

### `docs/design-system.md` addition
Add a "Design review checklist" section (human-readable, for PR reviewers) if A1.6 hasn't already
added one.

## Gate
- All three tests exist and pass against the current (unmigrated) codebase
- Each test's `it_fails_when_*` self-test also passes
- The adoption baseline JSON file is committed

Write `echo "A3" > .claude/current-step` then `./scripts/validate-step.sh A3`.
