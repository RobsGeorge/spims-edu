#!/usr/bin/env bash
# ./scripts/validate-wave.sh <wave-id>
# The merge gate. Runs AFTER all lanes in the wave have finished their per-step gates.
# Full suite runs here — not in validate-step.sh or the Stop hook.
# Exit 0 = safe to merge, exit 1 = DO NOT MERGE.
set -uo pipefail
wave="${1:?usage: validate-wave.sh <wave-id>}"
source "$(dirname "$0")/waves.sh"

steps=($(steps_for "$wave")) || { echo "Unknown wave: $wave" >&2; exit 1; }
fail=0

echo "====== validate-wave: [$wave] — lanes: ${steps[*]} ======"

# 1. Per-step gate for every lane
echo ""
echo "--- per-step gates ---"
for s in "${steps[@]}"; do
  echo "  checking [$s]..."
  if ! ./scripts/validate-step.sh "$s" 2>&1; then
    echo "  !! step [$s] gate FAILED" >&2
    fail=1
  fi
done

# 2. Full test suite (only here, once per wave)
echo ""
echo "--- full test suite ---"
if ! php artisan test 2>&1; then
  echo "!! full suite FAILED" >&2
  fail=1
fi

# 3. Adoption counters must not grow (design system regression guard)
echo ""
echo "--- adoption counters must not grow ---"
if ! php artisan test --filter="DesignSystemAdoptionTest" 2>&1; then
  echo "!! DesignSystemAdoptionTest FAILED — banned pattern count grew" >&2
  fail=1
fi

# 4. Cross-lane merge dry-run (detect conflicts between lanes before they hit main)
echo ""
echo "--- cross-lane merge dry-run ---"
base_ref=$(git rev-parse HEAD)
for s in "${steps[@]}"; do
  branch="worktree-$s"
  if git rev-parse --verify "$branch" >/dev/null 2>&1; then
    if ! git merge --no-commit --no-ff "$branch" >/dev/null 2>&1; then
      echo "!! lane [$s] conflicts with current HEAD" >&2
      fail=1
    fi
    git merge --abort 2>/dev/null || true
  else
    echo "  branch $branch not found — skipping dry-run for [$s]"
  fi
done

echo ""
echo "======================================"
if [[ $fail -eq 0 ]]; then
  echo "PASS: wave [$wave] — safe to merge"
else
  echo "FAIL: wave [$wave] — DO NOT MERGE. Review errors above." >&2
fi
exit $fail
