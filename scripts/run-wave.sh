#!/usr/bin/env bash
# ./scripts/run-wave.sh <wave-id>
# Runs every lane in the wave in parallel (one worktree per lane), then runs the wave gate.
# W1 (A1.1-A1.6) runs sequentially — it is a single-agent bottleneck by definition.
set -uo pipefail
wave="${1:?usage: run-wave.sh <wave-id>}"
source "$(dirname "$0")/waves.sh"
steps=($(steps_for "$wave")) || { echo "Unknown wave: $wave" >&2; exit 1; }

mkdir -p logs
: > logs/_wave-"$wave".log

echo "=== Starting wave [$wave]: ${steps[*]} ==="

if [[ "$wave" == "W1" ]]; then
  # A1 substeps run sequentially — each gate must pass before the next substep starts
  echo "=== W1 is sequential (bottleneck step) ==="
  for s in "${steps[@]}"; do
    echo "--- [$s] starting ---"
    echo "$s" > .claude/current-step
    prompt="$(cat prompts/GLOBAL-CONTRACT.md prompts/step-${s}.md 2>/dev/null)"
    if ! claude -p "$prompt" \
        --worktree "$s" \
        --permission-mode acceptEdits \
        --fallback-model sonnet \
        > "logs/${s}.log" 2>&1; then
      echo "[$s] FAILED — stopping W1" >&2
      echo "[$s] exit=1" >> logs/_wave-"$wave".log
      cat "logs/${s}.log" | tail -20
      exit 1
    fi
    echo "[$s] exit=0" >> logs/_wave-"$wave".log
    echo "--- [$s] done ---"
  done
else
  # All other waves: parallel
  for s in "${steps[@]}"; do
    (
      echo "$s" > .claude/worktrees/${s}/.claude/current-step 2>/dev/null || true
      prompt="$(cat prompts/GLOBAL-CONTRACT.md prompts/step-${s}.md 2>/dev/null)"
      claude -p "$prompt" \
        --worktree "$s" \
        --permission-mode acceptEdits \
        --fallback-model sonnet \
        > "logs/${s}.log" 2>&1
      echo "[$s] exit=$?" >> logs/_wave-"$wave".log
    ) &
  done
  wait
fi

echo ""
echo "=== All agents finished — wave [$wave] summary ==="
cat logs/_wave-"$wave".log

echo ""
echo "=== Running wave gate ==="
if ! ./scripts/validate-wave.sh "$wave"; then
  echo ""
  echo "!! Wave [$wave] FAILED the gate. Do NOT merge. Review logs/ and fix." >&2
  exit 1
fi

echo ""
echo "=== Wave [$wave] PASSED. Branches ready for review: ==="
for s in "${steps[@]}"; do
  echo "  worktree-$s:"
  git log --oneline main.."worktree-$s" 2>/dev/null | head -5 | sed 's/^/    /'
done

echo ""
echo "To clean up a lane after merging:"
echo "  git worktree unlock .claude/worktrees/<step> 2>/dev/null || true"
echo "  git worktree remove .claude/worktrees/<step> --force"
