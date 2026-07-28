#!/usr/bin/env bash
# Restore a SPIMS PostgreSQL dump. DESTROYS current DB contents for that database.
# Usage: ./scripts/restore-db.sh /var/backups/spims/spims_20260728_023000.sql.gz
set -euo pipefail

DUMP="${1:-}"
if [[ -z "$DUMP" || ! -f "$DUMP" ]]; then
  echo "Usage: $0 path/to/spims_YYYYMMDD_HHMMSS.sql.gz" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -f .env ]]; then
  # shellcheck disable=SC1091
  set -a; source .env; set +a
fi

export PGPASSWORD="${DB_PASSWORD:-}"
HOST="${DB_HOST:-127.0.0.1}"
PORT="${DB_PORT:-5432}"
USER="${DB_USERNAME:-spims}"
DB="${DB_DATABASE:-spims}"

echo "WARNING: restoring into ${DB}@${HOST}. Ctrl-C within 5s to abort."
sleep 5

gunzip -c "$DUMP" | psql -h "$HOST" -p "$PORT" -U "$USER" -d "$DB" -v ON_ERROR_STOP=1

echo "Restore complete. Run: php8.2 artisan migrate --force && php8.2 artisan optimize:clear"
