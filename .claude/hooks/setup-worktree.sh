#!/usr/bin/env bash
# WorktreeCreate hook — provisions a fresh worktree with vendor/, DB, and .env.
# Must print the absolute worktree path on stdout; everything else goes to stderr.
set -euo pipefail
input=$(cat)
name=$(jq -r '.name' <<<"$input")
repo="$CLAUDE_PROJECT_DIR"
wt="$repo/.claude/worktrees/$name"

echo "=== setup-worktree: $name → $wt ===" >&2
git -C "$repo" worktree add "$wt" -b "worktree-$name" >&2

cd "$wt"

# Copy environment files
cp "$repo/.env" .env 2>/dev/null && echo "Copied .env" >&2 || echo "No .env to copy — continuing" >&2
cp "$repo/.env.testing" .env.testing 2>/dev/null || true

# Reuse vendor/ from the main repo if present (avoids composer corrupting git remotes)
if [[ -d "$repo/vendor" ]]; then
  echo "Symlinking vendor/ from main repo..." >&2
  ln -s "$repo/vendor" vendor
else
  echo "Running composer install..." >&2
  # Run in a tmp dir so composer's own git context doesn't touch this worktree's .git
  COMPOSER_HOME="$(mktemp -d)" composer install --no-interaction --prefer-dist --working-dir="$wt" >&2
fi

# Boot the app only if .env was copied
if [[ -f .env ]]; then
  php artisan key:generate --force >&2
else
  echo "No .env — skipping key:generate (tests use .env.testing)" >&2
fi

# Tests use in-memory SQLite configured in phpunit.xml — RefreshDatabase handles migration per test.
# Only create the SQLite file if a persistent test DB is needed (it is not needed here).
touch database/database.sqlite 2>/dev/null || true

# Write the step ID so verify-step.sh knows what to validate
mkdir -p .claude
echo "$name" > .claude/current-step

echo "=== worktree ready ===" >&2
echo "$wt"
