# Step 14 — Gradebook Cell Entry + Proctor Review Polish

Step ID: `14`  
Wave: W3 | Design system native.

## Part A — Editable gradebook

The admin gradebook is currently read-only. Make each cell editable.

### Rules
- **Locked gradebook**: when the gradebook is locked (check the offering's `gradebook_locked_at`),
  show a prominent banner and reject all writes with a 422. The banner explains why and who to contact.
- **Per-cell edit**: click a cell → inline input appears → save → percent and letter recompute
  against the offering's grading scheme
- **Validation**: score must be within 0..max_score for that component
- **Audited**: every cell change goes through `AssignmentService` and writes an AuditLog entry
- **Blocked when locked**: a locked gradebook returns 403 on POST even without a banner (defense in depth)

### Gate (Part A)
- A locked gradebook rejects the write (422/403) AND shows the banner (both)
- Every cell edit is audited (AuditLog entry with old value + new value)
- Percent and letter recompute correctly: assert against the offering's grading scheme
- A cell edit outside the valid range is rejected with a validation error

## Part B — Proctor review view

The current proctor review view has hard-coded English strings and raw enums. Fix it.

Current hard-coded strings to replace: "terminated for cheating", "Proctor events", "type", "at"
Current raw enums: whatever enum is being printed with `->value` in the proctor view files

### Replace with
- A localized severity timeline using `<x-timeline>`
- Severity levels expressed via `<x-badge variant=...>` with lang keys
- All timestamps in the locale's date format
- Fully localized in ar, en, fr

### Gate (Part B)
- The proctor view has ZERO hard-coded English strings (grep for the specific strings above — all gone)
- ZERO raw enums visible in any locale
- Renders correctly under RTL (Arabic timeline direction)

`echo "14" > .claude/current-step` then `./scripts/validate-step.sh 14`
