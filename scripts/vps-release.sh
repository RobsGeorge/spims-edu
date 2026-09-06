#!/usr/bin/env bash
# SPIMS VPS release — migrate, cache, permissions, reload, health.
# Run as the deploy user from the application root AFTER git sync + composer.
#
# Usage:
#   HEALTH_URL=https://spims-edu.com QUEUE_UNIT=spims-queue ./scripts/vps-release.sh
#   HEALTH_URL=https://staging.spims-edu.com QUEUE_UNIT=spims-queue-staging ./scripts/vps-release.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-php8.2}"
DEPLOY_USER="$(whoami)"
QUEUE_UNIT="${QUEUE_UNIT:-spims-queue}"
HEALTH_URL="${HEALTH_URL:-}"
SKIP_MAINTENANCE="${SKIP_MAINTENANCE:-0}"

if [[ ! -f artisan || ! -f .env ]]; then
  echo "ERROR: $ROOT does not look like a SPIMS app root (missing artisan or .env)" >&2
  exit 1
fi

step() { echo "==> $1 ($(date -Iseconds))"; }

fix_runtime_permissions() {
  sudo -n chown -R "${DEPLOY_USER}:www-data" storage bootstrap/cache
  sudo -n find storage bootstrap/cache -type d -exec chmod 2775 {} +
  sudo -n find storage bootstrap/cache -type f -exec chmod 664 {} +
}

DEPLOY_START=$(date +%s)

step "reclaim storage for git"
sudo -n chown -R "${DEPLOY_USER}:www-data" storage bootstrap/cache
fix_runtime_permissions

if [[ "$SKIP_MAINTENANCE" != "1" ]]; then
  step "maintenance mode"
  $PHP_BIN artisan down --retry=60 --refresh=15 || true
fi

step "migrations"
$PHP_BIN artisan migrate --force

step "optimize"
$PHP_BIN artisan optimize:clear
mkdir -p storage/framework/cache/data
fix_runtime_permissions
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
fix_runtime_permissions

step "storage link"
if [[ ! -L public/storage ]]; then
  $PHP_BIN artisan storage:link
fi

step "reload php-fpm"
sudo -n systemctl reload php8.2-fpm

step "restart queue ${QUEUE_UNIT}"
sudo -n systemctl restart "$QUEUE_UNIT" || echo "WARN: ${QUEUE_UNIT} restart failed"

step "ensure scheduler cron"
$PHP_BIN artisan scheduler:ensure-cron --php="$PHP_BIN" --apply || echo "WARN: scheduler cron"

if [[ "$SKIP_MAINTENANCE" != "1" ]]; then
  step "live"
  $PHP_BIN artisan up || true
fi

# Loopback health: works before public DNS, proves nginx + php-fpm + DB.
if [[ -n "${LOOPBACK_HEALTH_HOST:-}" ]]; then
  step "loopback GET /health (Host: ${LOOPBACK_HEALTH_HOST})"
  curl -fsS --retry 5 --retry-delay 2 --max-time 15 \
    -H "Host: ${LOOPBACK_HEALTH_HOST}" \
    http://127.0.0.1/health | tee /tmp/spims-health.json
  grep -q '"status":"ok"' /tmp/spims-health.json
fi

if [[ -n "$HEALTH_URL" ]]; then
  step "public GET ${HEALTH_URL}/health"
  curl -fsS --retry 5 --retry-delay 3 --max-time 20 \
    "${HEALTH_URL%/}/health" | tee /tmp/spims-health-public.json
  grep -q '"status":"ok"' /tmp/spims-health-public.json

  step "public GET ${HEALTH_URL}/api/v1/branding"
  curl -fsS --retry 3 --retry-delay 2 --max-time 20 \
    "${HEALTH_URL%/}/api/v1/branding" | grep -q '"data"'
fi

DEPLOY_END=$(date +%s)
echo "Release complete in $((DEPLOY_END - DEPLOY_START))s"
