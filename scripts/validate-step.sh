#!/usr/bin/env bash
# ./scripts/validate-step.sh <step-id>
# Runs the scoped gate for one step. Called by the Stop hook and the wave runner.
# Exit 0 = pass, exit 1 = fail.
set -uo pipefail
step="${1:?usage: validate-step.sh <step-id>}"
tsv="${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null || pwd)}/prompts/steps.tsv"

row=$(grep -P "^${step}\t" "$tsv" 2>/dev/null) || {
  echo "Unknown step ID: $step (not found in prompts/steps.tsv)" >&2
  exit 1
}

filter=$(echo "$row" | cut -f2)
scope=$(echo "$row" | cut -f3)
fail=0

echo "====== validate-step: [$step] ======"

# 1. Scoped tests
echo "--- scoped tests: $filter"
if ! php artisan test --filter="$filter" 2>&1; then
  echo "!! test suite failed for filter: $filter" >&2
  fail=1
fi

# 2. Banned patterns in scope
echo "--- banned patterns in scope: $scope"
IFS=',' read -ra dirs <<<"$scope"
for d in "${dirs[@]}"; do
  [[ -e "$d" ]] || continue
  hits=$(grep -rnE \
    'card border-0 shadow-sm|class="[^"]*\btext-muted\b|->value\s*\}\}|(total|amount|price|balance|fee|cost)_minor\s*\}\}|\bbg-light\b|\bbg-secondary\b' \
    "$d" --include='*.blade.php' 2>/dev/null || true)
  if [[ -n "$hits" ]]; then
    echo "!! banned patterns found in $d:" >&2
    echo "$hits" >&2
    fail=1
  fi
done

# 3. Three-locale parity (always — lang drift is the most common silent regression)
echo "--- three-locale parity"
if ! php artisan test --filter="LocaleParityTest" 2>&1; then
  echo "!! LocaleParityTest failed — ar/en/fr keys are out of sync" >&2
  fail=1
fi

# 4. A2 scope-escape check — a presentation-only lane must not touch controllers/routes/migrations
if [[ "$step" == A2-* ]]; then
  echo "--- A2 scope-escape check"
  escaped=$(git diff --name-only main... 2>/dev/null | grep -E '^(routes/|app/Http/Controllers/|database/migrations/)' || true)
  if [[ -n "$escaped" ]]; then
    echo "!! A2 lane [$step] touched controller/route/migration files — presentation-only violated:" >&2
    echo "$escaped" >&2
    fail=1
  fi
fi

echo "======================================"
if [[ $fail -eq 0 ]]; then
  echo "PASS: [$step]"
else
  echo "FAIL: [$step] — see errors above" >&2
fi
exit $fail
