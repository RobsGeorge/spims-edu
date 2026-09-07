# Step 5 — Semester Calendar (Operator)

Step ID: `5`  
Wave: W3  
Scope: resources/views/admin/semesters, app/Services, routes

## What to build

### Year selector
Dropdown to switch between academic years. Preserves current view on switch.

### Per-semester timeline
Pure CSS (no JS for the layout). RTL-mirrored (test with `dir="rtl"`).
Three labelled regions per semester:
1. Registration window
2. Add/drop window
3. Teaching period
Today marker: a vertical line at the current date's position (CSS custom property set server-side).

### Status pills with state machine
`DRAFT → OPEN → IN_PROGRESS → CLOSED`
- Legal transitions: DRAFT→OPEN, OPEN→IN_PROGRESS, IN_PROGRESS→CLOSED
- Illegal transitions (e.g. CLOSED→DRAFT, OPEN→CLOSED directly): rejected with a validation error
- Each status change is audited via `AuditLogWriter::withAudit()`

### Add year / add semester
Via `<x-modal>` modals (not page navigations).

### Permissions
- View-only user: no controls visible, 403 on POST
- Admin: full controls

## Gate
- Every legal transition writes an audit entry (assert AuditLog)
- Every ILLEGAL transition is rejected — test the full matrix (not just one case):
  CLOSED→DRAFT, CLOSED→OPEN, CLOSED→IN_PROGRESS, OPEN→CLOSED, DRAFT→IN_PROGRESS, DRAFT→CLOSED
- View-only user: no buttons rendered AND 403 on POST (both, not just one)
- Today marker position mirrors correctly under RTL (assert `dir="rtl"` renders without errors)

`echo "5" > .claude/current-step` then `./scripts/validate-step.sh 5`
