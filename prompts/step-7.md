# Step 7 — Program Rule-Builder UI

Step ID: `7`  
Wave: W4 (needs Step 6 backend) | New UI — design system native.  
Scope: resources/views/admin/programs, app/Http/Controllers/Admin

## What to build
Add a rule-builder form to the program edit/show views.

### Fieldset groups
- **Identity**: name, code, type, level
- **Course-load**: max_credits_per_semester, max_courses_per_semester, `enforce_year_sequence` toggle
- **Time-to-graduation**: max_semesters_to_graduate
- **Requirements**: passing_threshold, require_all_courses_to_graduate
- **Credentialing**: certificate_template, issue_credential_on_completion

### `enforce_year_sequence` toggle
Labelled toggle in the Course-load or Requirements group.
Help text: *Off — students see a warning when taking courses out of the planned year order.*
          *On — the system blocks it; an admin must override.*
Default: off.

### Live rule-preview sentence
Alpine-driven client-side sentence that reads the form state and produces a human-readable
description: e.g. "Students may take up to 18 credits per semester, up to 8 semesters,
and must maintain a 60% grade to graduate. Year-order warnings are active."

The SAME sentence must appear on the program SHOW page, computed server-side from the saved data.
Assert both are identical for the same program state.

### Inline help
Each field has a `<small>` help text stating what the field actually enforces
(as implemented in Step 6, not as aspirational — be accurate).

### Localized enum labels
Program type, delivery mode, etc. — via lang keys, through `<x-badge>` where appropriate.

## Gate
- Client-side and server-side rule-preview sentences are identical for the same program
- Toggling `enforce_year_sequence` changes the preview sentence
- Every enum label resolves in ar, en, fr
- A user without `programs.manage` gets 403 on POST

`echo "7" > .claude/current-step` then `./scripts/validate-step.sh 7`
