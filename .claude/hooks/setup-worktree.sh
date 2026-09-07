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

# Install dependencies
echo "Running composer install..." >&2
composer install --no-interaction --prefer-dist >&2

# Boot the app
php artisan key:generate --force >&2

# SQLite test DB
touch database/database.sqlite
php artisan migrate --seed --env=testing >&2

# Write the step ID so verify-step.sh knows what to validate
mkdir -p .claude
echo "$name" > .claude/current-step

echo "=== worktree ready ===" >&2
echo "$wt"
