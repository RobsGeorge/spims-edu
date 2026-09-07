# Step 10 — Student Web UI for Surveys (audit → complete → migrate)

Step ID: `10`  
Wave: W3 | Design system native for new code; migrate existing views.

## IMPORTANT: Existing UI
Surveys already has student routes and views:
- `GET /surveys` → `SurveyController::index` (view: surveys/index.blade.php, 42 lines)
- `GET /surveys/{survey}` → `SurveyController::show` (view: surveys/show.blade.php, 76 lines)
- `POST /surveys/{survey}` → `SurveyController::submit`
- `surveys/partials/question.blade.php` (91 lines — question type rendering already exists)

## Substep 10.1 — Audit
Compare existing web views against the survey API capabilities. Identify gaps.

## Substep 10.2 — Complete
Build only the gaps. Expected:
- **All question types rendered** (check surveys/partials/question.blade.php covers every type
  in the `QuestionType` enum — add any missing types)
- **One-response rule**: a submitted survey shows a "Thank you" state instead of the form
- **Closed-window handling**: a survey past its closing date shows a closed message, not the form
- **Anonymous surveys**: for a survey marked anonymous, assert no query path links the submission
  to a user identity — this is the highest-consequence assertion in the step

## Substep 10.3 — Migrate
Apply design system migration to all surveys/ views. Localize in ar, en, fr.

## Gate
- Every question type in the QuestionType enum renders and submits
- A second submission is rejected (one-response rule enforced)
- A closed survey rejects submission
- For an anonymous survey: no query from the submission table back to a user identity exists
  (write this assertion explicitly — anonymous = structurally unlinked, not just "we don't display it")

`echo "10" > .claude/current-step` then `./scripts/validate-step.sh 10`
