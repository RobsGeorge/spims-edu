#!/usr/bin/env bash
# Daily PostgreSQL backup for SPIMS. Intended for cron or systemd timer.
# Usage: BACKUP_DIR=/var/backups/spims ./scripts/backup-db.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -f .env ]]; then
  # shellcheck disable=SC1091
  set -a; source .env; set +a
fi

BACKUP_DIR="${BACKUP_DIR:-${BACKUP_PATH:-/var/backups/spims}}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
STAMP="$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

export PGPASSWORD="${DB_PASSWORD:-}"
OUT="${BACKUP_DIR}/spims_${STAMP}.sql.gz"

pg_dump -h "${DB_HOST:-127.0.0.1}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-spims}" \
  -d "${DB_DATABASE:-spims}" --no-owner --no-acl | gzip -c > "$OUT"

echo "Wrote $OUT"
find "$BACKUP_DIR" -name 'spims_*.sql.gz' -mtime +"${RETENTION_DAYS}" -delete

# Optional off-site sync (configure rclone remote once):
# rclone copy "$BACKUP_DIR" remote:spims-backups --max-age "${RETENTION_DAYS}d"
