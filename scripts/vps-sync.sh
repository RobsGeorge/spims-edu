#!/usr/bin/env bash
# Fetch a git ref and install Composer production dependencies.
# Usage: GIT_REF=origin/main ./scripts/vps-sync.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-php8.2}"
GIT_REF="${GIT_REF:-origin/main}"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/local/bin/composer}"
DEPLOY_USER="$(whoami)"
SKIP_GIT="${SKIP_GIT:-0}"

if [[ ! -d .git ]]; then
  echo "ERROR: $ROOT is not a git checkout" >&2
  exit 1
fi

step() { echo "==> $1 ($(date -Iseconds))"; }

step "reclaim storage so git can write"
sudo -n chown -R "${DEPLOY_USER}:www-data" storage bootstrap/cache || true

if [[ "$SKIP_GIT" != "1" ]]; then
  step "git fetch + reset --hard ${GIT_REF}"
  git fetch --prune origin
  git reset --hard "$GIT_REF"
fi

step "composer install (no-dev)"
COMPOSER_ALLOW_SUPERUSER=1 $PHP_BIN "$COMPOSER_BIN" install \
  --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-progress --no-scripts
COMPOSER_ALLOW_SUPERUSER=1 $PHP_BIN "$COMPOSER_BIN" dump-autoload \
  --optimize --no-dev --no-interaction
