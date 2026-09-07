# Step 15 — Parity Sweep + Guardrail

Step ID: `15`  
Wave: W6 — LAST STEP. Must run after steps 9–14 are merged.

## What to build

### `RawEnumAndMinorUnitsGuardTest` (repo-wide)
Scans ALL Blade files for:
- Raw `->value` in output
- Raw `*_minor` integer output
Fails if any are found. Zero tolerance — these are now structurally impossible with `<x-badge>`
and `<x-money>`, so any remaining instances are legacy leftovers.

The test must also **demonstrate it can fail**: include a `@test` method that injects a temp file
with a raw enum and asserts the scan catches it.

### `docs/api-web-parity-matrix.md`
A table per domain, columns: Service | Student API | Student Web | Instructor Web | Admin Web

Seed it from the audit lists produced in Steps 9–12 (those PRs have the gap data).
**No blank cells**: every cell is either a route name/link or `deferred: <reason>`.
`deferred: out-of-scope` is not a valid reason — state the actual constraint.

Domains to cover at minimum:
Events, Surveys, Projects, LiveQuiz, Assignments, Assessments, Attendance, Finance,
Enrollment, Programs, Courses, Credentials, Communications, Users/Admin.

### CLAUDE.md addition
Add to CLAUDE.md:
```
## Parity rule
No new student or instructor API endpoint merges without shipping or explicitly deferring
its web equivalent in docs/api-web-parity-matrix.md.
```

## Gate
- `RawEnumAndMinorUnitsGuardTest` passes against the current codebase
- It fails correctly when fed a deliberate raw-enum injection (self-test method passes)
- The matrix has NO blank cells
- The CLAUDE.md parity rule is present

`echo "15" > .claude/current-step` then `./scripts/validate-step.sh 15`
