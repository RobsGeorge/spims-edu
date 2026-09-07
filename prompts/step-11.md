# Step 11 — Student Web Live Quiz Player (audit → complete → migrate)

Step ID: `11`  
Wave: W4 | Design system native for new/completed code; migrate existing views.

## IMPORTANT: Existing UI (thin)
- `GET /live-quiz/join` → `LiveQuizController::create` (view: live-quiz/join.blade.php, **17 lines**)
- `GET /live-quiz/sessions/{session}` → `LiveQuizController::show` (view: live-quiz/play.blade.php, 98 lines)
- `POST /live-quiz/sessions/{session}/questions/{question}/answer`

## Substep 11.1 — Audit
Compare to the full LiveQuiz API. Identify what's missing (state endpoint, results, etc.).

## Substep 11.2 — Complete
Build the gaps. Required:
- **`/state` JSON endpoint**: polled ~every 2s by Alpine. Returns current phase, question,
  deadline, server timestamp, and player count. Must be a separate lightweight endpoint.
- **Lobby phase**: waiting for the host to start. Shows participant count.
- **Question phase**: question text + options displayed. Countdown timer showing time remaining.
  **ALL timing from server-supplied deadline + server clock. Never compute a deadline from
  the client clock.** The state payload carries both `server_now` and `deadline_at`.
- **Locked phase**: answer submitted, waiting for next question or results.
- **Results phase**: shows correct answer, per-question scores, leaderboard.
- **Host-ended state**: graceful "Quiz ended by host" screen, not an error.
- Tap targets ≥44px for answer buttons — largest tap targets in the portal.

## Substep 11.3 — Migrate
Apply design system migration to live-quiz/ views.

## Gate
- Assert the client NEVER computes a deadline from `new Date()` or `Date.now()` alone —
  the state response carries `server_now` and `deadline_at` and the timer uses those
- An answer submitted after the server deadline is rejected (test by setting a past deadline)
- A host-ended session shows the ended state, not a JS error
- Answer tap targets ≥44px at 360px

`echo "11" > .claude/current-step` then `./scripts/validate-step.sh 11`
