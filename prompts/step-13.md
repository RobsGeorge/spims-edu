# Step 13 — Instructor Submission Grading Workbench

Step ID: `13`  
Wave: W3 | Highest instructor value in the pack. New UI — design system native.

## Context
Today the only path to grade online work is a `student_id,score,feedback` CSV textarea.
This step replaces that with a proper grading interface, built in three substeps.

---

## Substep 13.1 — Submissions Roster

Build the submissions list view for a given assignment.

### Features
- Status column: not_submitted, submitted, graded (via `<x-badge>`)
- Version number (latest version)
- Score column (if graded, numeric + `tabular-nums`)
- Filters: status (all / ungraded / graded), submission date range
- "Grade next ungraded" button: navigates directly to the next ungraded submission

### Gate 13.1
- Roster paginates (assert with 30+ seeded submissions)
- Every filter composes (status + date range simultaneously)
- "Grade next ungraded" skips already-graded submissions
- "Grade next ungraded" terminates cleanly (shows a "Nothing left to grade" state) when all are graded
- A grader without `assessments.grade` permission gets 403

---

## Substep 13.2 — Single-Submission View + Grading

Build the per-submission view.

### Layout
- Desktop: content + files + version history on the LEFT, grading panel on the RIGHT
- Phone (360px): single column, content first then grading panel below

### Content area (left)
- Submission content (rendered safely — no raw HTML from student input)
- Attached files: filename, size, download link
- Version history: list of all submitted versions with timestamps (latest highlighted)

### Grading panel (right)
- Score input (validated against the assignment's max score)
- Feedback textarea
- Submit button: "Save grade" — goes through `AssignmentService`, audited
- A graded submission shows the saved grade and feedback; the form is still editable (can revise)

### Gate 13.2
- Grading writes through `AssignmentService` (assert via the service, not directly to DB)
- Every grade save is audited (AuditLog entry exists)
- Version history shows ALL versions (not just the latest)
- Layout is single-column at 360px (assert no `col-md-` grid at small breakpoint)
- A grader without `assessments.grade` gets 403

---

## Substep 13.3 — AI Suggest (EssayAiGrader)

The `EssayAiGrader` service exists but is unused. Surface it as an editable suggestion.

### UI
- "AI Suggest" button in the grading panel — visible only for text/essay submissions
- On click: calls `EssayAiGrader` and populates the score and feedback fields as **editable pre-fill**
- A clearly visible label: "AI-generated suggestion — review before saving"
- The instructor can edit or discard the suggestion entirely and grade manually

### Hard rules
- The AI suggestion is NEVER auto-saved. No write occurs on suggest. Assert this in the test.
- The label must be visible and describe the AI origin — it cannot be hidden or subtle
- A "Dismiss suggestion" action must exist to clear the pre-fill

### Gate 13.3
- Assert NO write occurs when the suggest button is clicked (no new AuditLog, no DB change)
- The suggestion appears in the form fields as editable text (not locked)
- The instructor can clear the suggestion and submit a completely different grade
- The label describing AI origin is present in the rendered HTML

`echo "13" > .claude/current-step` then `./scripts/validate-step.sh 13`
