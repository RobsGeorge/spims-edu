=== STEP W3-b — Grades View Enhancement ===

SCOPE (presentation-only — no controller, route, or migration changes):
  resources/views/grades/index.blade.php
  lang/{ar,en,fr}/learning.php   (append keys only)

GOAL:
Turn the grades table from a raw data dump into a readable academic record with proper status badges
and a course-level summary stat.

CURRENT STATE:
- Status column shows raw strings: NOT_STARTED, SUBMITTED, PASSED, FAILED, etc.
- No visual distinction between passing and failing scores.
- Running grade is a small footnote; not prominent.

CHANGES REQUIRED:

1. STATUS BADGES in the grade table
   The `$item['status']` string comes from the grades service as one of:
   NOT_STARTED, SUBMITTED, PASSED, FAILED, GRADED, PENDING
   Map it to a status-badge:
   ```blade
   @php
     $statusMap = [
       'PASSED'      => 'success',
       'GRADED'      => 'success',
       'FAILED'      => 'danger',
       'SUBMITTED'   => 'info',
       'PENDING'     => 'warning',
       'NOT_STARTED' => 'secondary',
     ];
     $statusBadge = $statusMap[$item['status']] ?? 'secondary';
   @endphp
   <x-status-badge :status="$statusBadge" :label="__('learning.grade_status_'.strtolower($item['status']))" />
   ```
   Add lang keys for all six statuses in all three locales.

2. RUNNING GRADE as a prominent stat
   In each course card header, display the running grade % in a large readable format instead of
   embedding it in a small dim paragraph. Use:
   ```blade
   <div class="d-flex align-items-baseline gap-2 mb-1">
       <span class="h3 mb-0 spims-title">
           {{ $row['running_percent'] !== null ? number_format($row['running_percent'], 1).'%' : '—' }}
       </span>
       @if($row['final_letter'])
           <span class="h5 mb-0 spims-text-dim">{{ $row['final_letter'] }}</span>
       @endif
   </div>
   <p class="small spims-text-dim mb-0">{{ __('learning.running_grade') }}</p>
   ```
   Keep the "open player" button in the same header row on the right.

3. SCORE COLUMN — color-code passing vs failing
   Wrap the score display in a conditional class:
   - score >= 60 (or not null and passing): `class="text-success fw-semibold"`
   - score < 60: `class="text-danger fw-semibold"`
   - null: render `—` with `spims-text-dim`

LANG KEYS TO ADD (all three locales):
  learning.grade_status_not_started  → "Not started" / "لم يبدأ" / "Non commencé"
  learning.grade_status_submitted    → "Submitted"   / "مُرسَل"  / "Soumis"
  learning.grade_status_passed       → "Passed"      / "ناجح"    / "Réussi"
  learning.grade_status_failed       → "Failed"      / "راسب"    / "Échoué"
  learning.grade_status_graded       → "Graded"      / "مُقيَّم"  / "Noté"
  learning.grade_status_pending      → "Pending"     / "قيد الانتظار" / "En attente"

RULES:
- Presentation-only. Do NOT touch any controller, route, or migration.
- Every string goes in lang/{ar,en,fr}/learning.php.
- No banned patterns (text-muted, bg-light, bg-secondary, card border-0 shadow-sm, raw ->value in output).
- text-success / text-danger are Bootstrap semantic colors — acceptable here because they track
  pass/fail semantics, NOT because they are the preferred token approach. Do not use them for
  anything else.
- Mobile-first: the table already uses .spims-table-wrap; ensure the score column stays legible
  at 360px.

DONE WHEN:
- `./scripts/validate-step.sh W3-b` exits 0 (PASS).
- Status cells show colored badges, not raw strings.
- Running grade is the visual focal point of each course card.
- Committed and pushed to the worktree branch.
