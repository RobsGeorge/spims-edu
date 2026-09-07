#!/usr/bin/env bash
# Runs scoped validation for the current step before the agent can declare done.
# Full suite runs only once per wave in scripts/validate-wave.sh.
set -uo pipefail
input=$(cat)
cwd=$(jq -r '.cwd' <<<"$input")
cd "$cwd" || exit 0

step_file=".claude/current-step"
if [[ ! -f "$step_file" ]]; then
  # No step declared — interactive or non-wave session; pass through.
  exit 0
fi

step=$(cat "$step_file" | tr -d '[:space:]')
if [[ -z "$step" ]]; then
  # Empty file — treat same as absent; pass through.
  exit 0
fi

validate_script="./scripts/validate-step.sh"
if [[ ! -x "$validate_script" ]]; then
  echo "scripts/validate-step.sh not found or not executable in $cwd" >&2
  exit 2
fi

if ! out=$("$validate_script" "$step" 2>&1); then
  echo "Step [$step] validation FAILED. Fix before declaring done:" >&2
  echo "$out" | tail -60 >&2
  exit 2
fi

echo "Step [$step] validation PASSED."
exit 0
