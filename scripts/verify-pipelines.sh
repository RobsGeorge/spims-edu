#!/usr/bin/env bash
# Syntax-check deploy/CI artifacts. Used by the lint job and local `composer lint`.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

fail=0

check_bash() {
  local file="$1"
  if bash -n "$file"; then
    echo "OK  bash -n $file"
  else
    echo "FAIL bash -n $file" >&2
    fail=1
  fi
}

check_exists() {
  local file="$1"
  if [[ -f "$file" ]]; then
    echo "OK  exists $file"
  else
    echo "FAIL missing $file" >&2
    fail=1
  fi
}

check_bash scripts/vps-release.sh
check_bash scripts/vps-sync.sh
check_bash scripts/verify-pipelines.sh
check_bash scripts/backup-db.sh
check_bash scripts/restore-db.sh
check_bash deploy/first-boot.sh

check_exists .github/workflows/ci.yml
check_exists .github/workflows/deploy.yml
check_exists .github/workflows/deploy-staging.yml
check_exists .github/workflows/rollback.yml
check_exists deploy/nginx/spims.conf
check_exists deploy/systemd/spims-queue.service
check_exists deploy/systemd/spims-queue-staging.service
check_exists docs/owner-actions.md
check_exists docs/mobile-api-runtime.md

for wf in .github/workflows/*.yml; do
  if grep -q '^name:' "$wf" && grep -q '^on:' "$wf" && grep -q '^jobs:' "$wf"; then
    echo "OK  workflow shape $wf"
  else
    echo "FAIL workflow shape $wf" >&2
    fail=1
  fi
done

if grep -q 'uses: ./.github/workflows/ci.yml' .github/workflows/deploy.yml \
  && grep -q 'uses: ./.github/workflows/ci.yml' .github/workflows/deploy-staging.yml; then
  echo "OK  deploy workflows call CI"
else
  echo "FAIL deploy workflows must call CI" >&2
  fail=1
fi

if grep -q 'cancel-in-progress: false' .github/workflows/deploy.yml; then
  echo "OK  production deploy does not cancel in-progress releases"
else
  echo "FAIL production deploy must not cancel an in-progress release" >&2
  fail=1
fi

if [[ "$fail" -ne 0 ]]; then
  echo "Pipeline verification failed" >&2
  exit 1
fi

echo "Pipeline verification passed"
