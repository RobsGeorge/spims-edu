# Step 12 — Student Web Projects & Peer Evaluation (audit → complete → migrate)

Step ID: `12`  
Wave: W3 | Design system native for new/completed code; migrate existing views.

## IMPORTANT: Existing UI
Projects already has student routes and views:
- `GET /projects` → index (view: projects/index.blade.php, 77 lines)
- `GET /projects/mine` → mine (view: projects/mine.blade.php, 41 lines)
- `GET /projects/{p}` → show (view: projects/show.blade.php, 112 lines)

## Substep 12.1 — Audit
Compare to the Projects API. Identify gaps.

## Substep 12.2 — Complete
- **Deliverable submit**: real file upload via `StorageService` — not a URL textarea. The current
  URL textarea approach is replaced entirely. Enforce file-type and size limits.
- **Peer evaluation**: form to evaluate teammates. Locked (read-only) after submission.
- **Non-member 403**: a student who is not a project member gets 403 on every project route
  (including the show page — check `$project->members->contains(auth()->user())`)

## Substep 12.3 — Migrate
Apply design system migration to projects/ views.

## Gate
- A non-member gets 403 on every project route (index, mine, show, submit, peer-eval)
- A peer evaluation cannot be edited after submission (the form is gone; the submitted eval is shown read-only)
- Upload goes through StorageService: assert the file appears in `storage/app/` (or configured disk),
  not that a URL string was saved to the DB
- File-type and size limits are enforced (reject an oversized file with a user-facing error)

`echo "12" > .claude/current-step` then `./scripts/validate-step.sh 12`
