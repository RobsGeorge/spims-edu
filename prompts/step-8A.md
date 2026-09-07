# Step 8A — Demo Dataset + spims:demo-reset

Step ID: `8A`  
Wave: W4 (needs Step 1 columns for descriptions) | Backend only.  
Scope: database/seeders

## IMPORTANT: DemoDataSeeder already exists
`database/seeders/DemoDataSeeder.php` is already present with 16 accounts (password `Spims@Test2026!`).
**Extend it, do not replace it.** `DemoDataSeederTest` also exists — extend it, don't overwrite.

## Substep 8A.1 — Content & catalog
- Add `programs.description`, `programs.marketing_summary`, `courses.description` (seeded from
  Step 1's migration — assert the columns exist before seeding)
- Build a fully-loaded walkthrough offering for `student1@spims.test`:
  - Every content-item type (video, PDF, SCORM, text block, link)
  - Open assessment with questions
  - Discussion thread with at least one reply
  - Future live session
  - Released grades

## Substep 8A.2 — Finance & lifecycle state
- Paid invoice + open invoice for `student1`
- Wallet with balance across all relevant buckets
- An application in EVERY status (pending, under_review, accepted, rejected, withdrawn)
- Semesters: one CLOSED, one IN_PROGRESS, one DRAFT
- Issued credential with a printed, valid verify serial that the verifier widget accepts

## Substep 8A.3 — Parity surfaces & rule branches
- **Event**: published event with open seats for student1 to reserve
- **Survey**: open survey with at least one question of each type
- **Live quiz**: a created live quiz (not yet started) that student1 can join
- **Team project**: a project with student1 as a member, with a deliverable slot open
- **`enforce_year_sequence = true` program**: seed one program with the flag set,
  with courses at multiple year levels, so both branches of Step 6c are demonstrable

## Substep 8A.4 — `spims:demo-reset` artisan command
- Idempotent: re-running wipes and re-seeds demo state without touching non-demo users
- Identifies demo accounts by a `is_demo = true` flag or a known email prefix
- Running twice must produce identical state (assert this in the test)

## Gate (extend DemoDataSeederTest):
- Every seeded fixture is reachable (the walkthrough offering loads, the credential verifies)
- `spims:demo-reset` run twice leaves identical state (idempotency check)
- The verify serial actually validates through the credential verifier endpoint
- Both `enforce_year_sequence` branches have a seeded program

`echo "8A" > .claude/current-step` then `./scripts/validate-step.sh 8A`
