# S-branch reconciliation (audit only)

**Date:** 2026-09-07
**Auditor worktree:** `/tmp/spims-wt/p-audit` on `cursor/s-branch-reconciliation-ff2c`
**Base:** `origin/main` @ [`1c21d61081e666277b8b7c34c96db663f6373b4c`](https://github.com/RobsGeorge/spims-edu/commit/1c21d61081e666277b8b7c34c96db663f6373b4c) (`1c21d61` — *Merge branch 'cursor/payment-plans-gateways-ff2c'*)
**Method:** for each remote, `git log --oneline origin/main..<branch>`, `git diff --stat origin/main...<branch>`, `git merge-base --is-ancestor <branch> origin/main`, then `test -f` / grep of 3–8 tip files against this worktree (main).
**Scope:** leftover `s4`–`s8` remotes listed below. No merge, no cherry-pick, no feature work.

**Result:** every assigned tip is an **ancestor of `main`**. Triple-dot diffs are empty. Sampled classes, routes, and tests already exist on `main` with equivalent (or later-refined) behaviour. There is **nothing to cherry-pick** and **no alternate parked design** among these remotes.

---

## Summary

| Branch | Tip SHA | Commits ahead | Classification | One-line reason |
|---|---|---|---|---|
| `origin/cursor/s4-completion-api-bcff` | `f87695a184f5c7047d6d37efdf6d6501ee9e85ef` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `626ed68`. Completion + credentials `/api/v1` on main. |
| `origin/cursor/s4-completion-credentials-bcff` | `057698e2453b219387ae1656227b601c177e824f` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `0e77972` / follow-up `ee41dc7`. Domain + closing UI on main. |
| `origin/cursor/s5-assessment-completion-bcff` | `c2ad2302377cd5cf2a39b536a02496e30123d30f` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `1ab4e3d`. S5 tests + `ProctorService` / `ResultsVisibilityService` on main. |
| `origin/cursor/s6-student-api-bcff` | `ba08dc0a0d296827bd119da8051a13dbe981f91a` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `767f925`. Waves A–D + unpublished-write / pay idempotency on main. |
| `origin/cursor/s6e-events-bcff` | `430f0de890a2711650026835da8f445e83d0714e` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `e5ccb99`. Events domain, student API, QR check-in on main. |
| `origin/cursor/s6e-livequiz-bcff` | `a945ba361f40b6c30116411f0b35446bd51e6f09` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `9244c7f`. Live quiz host + poll play on main (not an alternate S9). |
| `origin/cursor/s6e-projects-bcff` | `6a1ec0bd024ca9f0a4e59070bec00c8755078da7` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `6780e8f`. Peer-eval close reload + two-team fixtures on main. |
| `origin/cursor/s6e-surveys-bcff` | `fdc9804ff0b8f87e2e08033b9ce56d782c9a9cbb` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `351ffb1`. Feedback surveys + fixture fix on main. |
| `origin/cursor/s8-blade-bcff` | `4eb4c45b72807f865c06a1b1498fe563cdc78b85` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `290e512`. Thin Blade staff UIs on main. |
| `origin/cursor/s8-client-rtl-etag-bcff` | `ac21fd306060f3bf79a572167ad7962caacbd7ba` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `3cb55bb`. Remaining teach ETags + contract test on main. |
| `origin/cursor/s8-core-bcff` | `62935c697c1754227245ab37552536ac30141d42` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `a9e7514`. Teach offerings, confirmation, attendance on main. |
| `origin/cursor/s8-grading-bcff` | `ff874ab393819c7787834dd8cb4a35d3b68f5364` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `76a4ed5`. Gradebook / assignment / assessment teach API on main. |
| `origin/cursor/s8-hardening-bcff` | `15c20643e89621585cf93279db9e021eade59ee7` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `8e58839`. `ConditionalGet` 304 `JsonResponse` on main. |
| `origin/cursor/s8-ops-bcff` | `091b125a2a9f79cafd790df8188ba95037fef8a2` | 0 | **ALREADY ANCESTOR / EMPTY** | Merged as `bbac90d`. Live / projects / discussions / content teach API on main. |

Two-dot `git diff origin/main <branch>` is large only because **main is 108–184 commits ahead** (later work). Triple-dot `origin/main...<branch>` is empty for every row: the branch introduced no file that main does not already contain.

---

## Do not re-implement

Starting prompts that rebuild these surfaces would **duplicate `main` @ `1c21d61`**. Do not re-open S4–S8 (or S6E / S9 domain) as greenfield work.

| Domain | Already on main (do not rebuild) |
|---|---|
| **S4 completion / credentials** | `CompletionService`, offering closing, certificate templates, `CompletionController`, `CredentialController`, `TeachCompletionController`, `tests/Feature/Completion/*`, `tests/Feature/Api/CompletionApiTest.php` |
| **S5 proctor / announce / offline** | `ProctorService`, `ResultsVisibilityService`, assignment dashboard / reminder / resubmit / offline, `ProctorEscalationTest`, `ResultsAnnouncementTest` |
| **S6 student API waves A–D** | Student `/api/v1` controllers for dashboard, offerings, assignments, assessments, invoices, applications; `StudentWaveCTest`, `StudentWaveDTest`; unpublished-write guard; `Idempotency-Key` on pay |
| **S6E / S7 projects** | Project domain + student API + peer eval (never grades); `PeerEvaluationService`, `ProjectsApiTest`, `StudentWaveEProjectsTest` |
| **S6E surveys** | `FeedbackSurveyController`, anonymity / reveal tests, `StudentWaveESurveysTest` |
| **S6E / S9 events** | `EventService`, `EventCheckInService`, student events API, QR verify; `EventReservationTest`, `EventCheckInTest` |
| **S6E / S9 live quiz** | `LiveQuizHostService`, `LiveQuizPlayService`, student poll + host teach API; lifecycle / scoring / fallback / scope tests. Polling is the shipped design. |
| **S8 instructor API** | `TeachOfferingController`, grading, ops (live / projects / discussions / content), confirmation tokens, ETags, idempotency, TA denials |
| **S8 Blade staff UIs** | Admin events, staff surveys, teach projects / live quiz, `tests/Feature/StaffUi/*` |

Prompts historically numbered **#10, #11, #14, #18–#20** in the S-series kickoff are the ones that would collide. Treat them as cancelled.

---

## Per-branch notes

Each section: leftover commit list (empty), triple-dot diff (empty), sampled files on this worktree, merge that absorbed the tip.

### `origin/cursor/s4-completion-api-bcff`

- **Tip:** `f87695a` — `feat(api): add S4 completion and credentials /api/v1 surface`
- **`git log origin/main..branch`:** empty
- **`git diff --stat origin/main...branch`:** empty
- **Ancestor of main:** yes (145 commits behind)
- **Landed via:** `626ed68` merge: S4 completion and credentials `/api/v1` surface
- **Sampled on main (all exist):**
  - `app/Http/Controllers/Api/V1/CompletionController.php`
  - `app/Http/Controllers/Api/V1/CredentialController.php`
  - `app/Http/Controllers/Api/V1/TeachCompletionController.php`
  - `app/Services/Credentials/CredentialService.php`
  - `tests/Feature/Api/CompletionApiTest.php`
  - `tests/Feature/Completion/StudentNotesPrivacyTest.php`
  - `routes/api.php` still registers `/me/credentials`, `/credentials/{credential}/download`, `/offerings/{offering}/completion`, evaluate, teach notes / week assessment
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s4-completion-credentials-bcff`

- **Tip:** `057698e` — `fix(completion): harden S4 closing UI, boolean flags, and criterion tests`
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (161 behind)
- **Landed via:** `0e77972` (domain merge) then `ee41dc7` (this follow-up)
- **Sampled on main (all exist):**
  - `app/Services/Completion/CompletionService.php` (`evaluate()`)
  - `app/Http/Controllers/Admin/OfferingClosingController.php`
  - `resources/views/admin/offering-closing/show.blade.php`
  - `tests/Feature/Completion/ClosingWorkflowTest.php`
  - `tests/Feature/Completion/CompletionCriteriaTest.php`
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s5-assessment-completion-bcff`

- **Tip:** `c2ad230` — S5 coverage (dashboard, reminders, resubmission, offline, proctor, announce)
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (160 behind)
- **Landed via:** `1ab4e3d` Merge branch `cursor/s5-assessment-completion-bcff`
- **Sampled on main (all exist):**
  - `tests/Feature/Assessment/AssignmentDashboardTest.php`
  - `tests/Feature/Assessment/AssignmentReminderTest.php`
  - `tests/Feature/Assessment/AssignmentOfflineTest.php`
  - `tests/Feature/Assessment/ProctorEscalationTest.php`
  - `tests/Feature/Assessment/ResultsAnnouncementTest.php`
  - `app/Services/Assessment/ProctorService.php` (`recordEvent`, `clearTermination`)
  - `app/Services/Assessment/ResultsVisibilityService.php` (`scoresVisible`, `answersVisible`)
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s6-student-api-bcff`

- **Tip:** `ba08dc0` — `fix(api): block unpublished writes and honor Idempotency-Key on pay`
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (141 behind)
- **Landed via:** `767f925` merge: S6 student mobile API waves A–D
- **Sampled on main (all exist):**
  - `app/Http/Controllers/Api/V1/AssignmentController.php`
  - `app/Http/Controllers/Api/V1/AssessmentController.php`
  - `app/Http/Controllers/Api/V1/InvoiceController.php`
  - `tests/Feature/Api/StudentWaveCTest.php`
  - `tests/Feature/Api/StudentWaveDTest.php`
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s6e-events-bcff`

- **Tip:** `430f0de` — `feat(s6e): events domain, student API, and QR check-in`
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (136 behind)
- **Landed via:** `e5ccb99` merge: S6E events and reservations
- **Sampled on main (all exist):**
  - `app/Services/Events/EventService.php`
  - `app/Services/Events/EventCheckInService.php` (`issueQr`, `verify`)
  - `app/Http/Controllers/Api/V1/EventController.php`
  - `tests/Feature/Events/EventReservationTest.php`
  - `tests/Feature/Events/EventCheckInTest.php`
  - `tests/Feature/Api/StudentWaveEEventsTest.php`
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s6e-livequiz-bcff`

- **Tip:** `a945ba3` — Add S9 live quiz domain, student poll API, and host service
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (136 behind)
- **Landed via:** `9244c7f` merge: S6E live quiz
- **Sampled on main (all exist):**
  - `app/Services/LiveQuiz/LiveQuizHostService.php` (`startSession`)
  - `app/Services/LiveQuiz/LiveQuizPlayService.php`
  - `app/Http/Controllers/Api/V1/LiveQuizController.php`
  - `app/Http/Controllers/Api/V1/TeachLiveQuizController.php`
  - `tests/Feature/LiveQuiz/LiveQuizLifecycleTest.php`
  - `tests/Feature/LiveQuiz/LiveQuizFallbackTest.php`
- **Not LEAVE PARKED:** this *is* the design on main (polling, Reverb optional). There is no leftover alternate live-quiz architecture on this remote.
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s6e-projects-bcff`

- **Tip:** `6a1ec0b` — Fix peer-eval close reload and explicit two-team change-request fixtures
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (135 behind)
- **Landed via:** `6780e8f` merge: S6E team projects
- **Sampled on main (all exist):**
  - `app/Services/Projects/PeerEvaluationService.php` (`open`, `close`, `submit`)
  - `tests/Feature/Api/ProjectsApiTest.php`
  - `tests/Feature/Api/StudentWaveEProjectsTest.php`
  - `tests/Feature/Projects/ProjectChangeRequestTest.php`
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s6e-surveys-bcff`

- **Tip:** `fdc9804` — `fix(feedback): import API Controller and drop fixture trait collision`
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (135 behind)
- **Landed via:** `351ffb1` merge: S6E feedback surveys
- **Sampled on main (all exist):**
  - `app/Http/Controllers/Api/V1/FeedbackSurveyController.php`
  - `tests/Feature/Api/StudentWaveESurveysTest.php`
  - `tests/Feature/Feedback/SurveyLifecycleTest.php`
  - `tests/Feature/Feedback/IdentityRevealTest.php`
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s8-blade-bcff`

- **Tip:** `4eb4c45` — Add thin Blade staff UIs for Wave E surveys, events, projects, and live quiz
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (125 behind)
- **Landed via:** `290e512` merge: thin Blade staff UIs
- **Sampled on main (all exist):**
  - `app/Http/Controllers/Admin/EventAdminController.php`
  - `app/Http/Controllers/Teach/LiveQuizController.php`
  - `app/Http/Controllers/Teach/ProjectController.php`
  - `app/Http/Controllers/Teach/SurveyController.php`
  - `tests/Feature/StaffUi/StaffEventUiTest.php`
  - `tests/Feature/StaffUi/StaffLiveQuizUiTest.php`
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s8-client-rtl-etag-bcff`

- **Tip:** `ac21fd3` — `feat(api): add ETags on remaining safe teach GETs`
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (108 behind)
- **Landed via:** `3cb55bb` merge: S8 client contract (idempotency, Arabic viewport, remaining ETags)
- **Sampled on main (all exist):**
  - `tests/Feature/Api/InstructorTeachEtagContractTest.php` (307 lines)
  - `app/Http/Controllers/Api/V1/TeachAssignmentController.php`
  - `app/Http/Controllers/Api/V1/TeachAttendanceController.php`
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s8-core-bcff`

- **Tip:** `62935c6` — `feat(api): add S8 core instructor offering endpoints`
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (125 behind)
- **Landed via:** `a9e7514` merge: S8 instructor API core
- **Sampled on main (all exist):**
  - `app/Http/Controllers/Api/V1/TeachOfferingController.php`
  - `app/Support/Api/ConfirmationToken.php` (`issue`, `consume`)
  - `tests/Feature/Api/InstructorApiScopeTest.php`
  - `tests/Feature/Api/InstructorApiRoleTest.php`
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s8-grading-bcff`

- **Tip:** `ff874ab` — `feat(api): add S8 instructor grading, lock confirmation, and tests`
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (125 behind)
- **Landed via:** `76a4ed5` merge: S8 instructor grading API
- **Sampled on main (all exist):**
  - `app/Http/Controllers/Api/V1/TeachGradebookController.php`
  - `app/Http/Controllers/Api/V1/TeachAssessmentGradingController.php`
  - `app/Http/Controllers/Api/V1/TeachAssignmentController.php`
  - `tests/Feature/Api/InstructorGradingApiTest.php`
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s8-hardening-bcff`

- **Tip:** `15c2064` — `fix: return a JsonResponse for teach list 304s`
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (112 behind)
- **Landed via:** `8e58839` merge: S8 instructor API hardening
- **Sampled on main:** `app/Support/Api/ConditionalGet.php` still returns `(new JsonResponse(null, 304))->setEtag($etag)` — the tip’s one-line fix is present.
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

### `origin/cursor/s8-ops-bcff`

- **Tip:** `091b125` — `feat(api): add S8 instructor ops for live, projects, discussions, and content`
- **Commits ahead / triple-dot:** empty
- **Ancestor of main:** yes (125 behind)
- **Landed via:** `bbac90d` merge: S8 instructor ops API
- **Sampled on main (all exist):**
  - `app/Http/Controllers/Api/V1/TeachContentController.php`
  - `app/Http/Controllers/Api/V1/TeachDiscussionController.php`
  - `app/Http/Controllers/Api/V1/TeachLiveSessionController.php`
  - `app/Http/Controllers/Api/V1/TeachProjectController.php`
  - `tests/Feature/Api/InstructorOpsContentTest.php`
  - `tests/Feature/Api/InstructorOpsProjectsTest.php`
- **Leftover unique files:** none
- **Classification:** ALREADY ANCESTOR / EMPTY — safe to delete

---

## Adjacent remotes (not in the assigned table)

Also present and **ancestors of `main`** (0 commits ahead). Same delete hygiene if a human is sweeping `cursor/s*` leftovers:

| Branch | Tip | Behind main |
|---|---|---|
| `origin/cursor/s2-communications-spine-bcff` | `4ed38f0` | 181 |
| `origin/cursor/s3-attendance-roster-bcff` | `e634aaf` | 184 |

This audit did not re-sample S2/S3 files; README already records those phases as landed.

---

## Status vs this audit (`implementation-plan.md` / `execution-order.md`)

Plans were skimmed, not rewritten. Against `main` @ `1c21d61`:

| Plan item | Satisfied on main? | Still open |
|---|---|---|
| S0 resource scope | Yes | — |
| S1 `/api/v1` foundation | Yes | — |
| S2 communications spine | Yes | WhatsApp *driver* still parked |
| S3 attendance + roster | Yes | — |
| S4 completion + credentials | **Yes** (heading in `implementation-plan.md` still lacks ✅) | — |
| S5 assessment completion | **Yes** (heading still lacks ✅; G-17 overwrite already ticked) | — |
| S6 student API A–D | **Yes** | — |
| S6 Wave E (surveys, events, projects, live quiz) | **Yes** | — |
| S7 team projects | **Yes** (heading still lacks ✅) | — |
| S8 instructor API + Blade staff UIs | Yes (already ✅) | `gradebook.reopen` is Academic Admin / web only — not on the instructor API |
| S9 events + live quiz domain | **Yes** (polling) | Laravel Reverb still optional / parked |
| S9 Reverb / websocket transport | No — parked by design | See `PARKING-LOT.md` |

`execution-order.md` already says S0–S8 and S6 Wave E are complete and S9 domain shipped with polling. That matches this audit. The only docs drift is `implementation-plan.md` still reading as in-progress for S4, S5, S6, S7, and S9 headings.

Open work that is **not** sitting on these remotes: Reverb, WhatsApp driver, lockdown browser, native apps, Title IV / library / bookstore / housing / SCORM / LTI, parent role, multi-tenant. Those stay in `PARKING-LOT.md`.

---

## Recommended next action (humans)

1. **Delete all 14 remotes in the summary table.** They are fast-forward ancestors of `main`. Keeping them invites a later agent to treat the names as unfinished S4–S8 work.
2. **Optionally delete** `origin/cursor/s2-communications-spine-bcff` and `origin/cursor/s3-attendance-roster-bcff` in the same sweep (also ancestors).
3. **Do not write a cherry-pick or polish prompt against these branches.** There are no leftover commits or unique files. A future polish prompt should target *gaps on current `main`* (Reverb optional ops, `gradebook.reopen` on teach API if product wants it) — not a replay of S4–S8.
4. **Cancel** any queued S-series implementation prompts (#10, #11, #14, #18–#20 style) that would add Completion / Proctor / student waves / events / live quiz / instructor teach controllers again.
5. Optional docs tidy (separate from this audit): tick S4 / S5 / S6 / S7 / S9 headings in `implementation-plan.md` so they match `README.md`.

**Cherry-pick candidates:** none.
**Leave parked (unique colliding design):** none among these remotes.
