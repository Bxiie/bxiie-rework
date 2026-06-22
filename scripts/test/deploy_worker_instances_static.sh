#!/bin/bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEPLOY="$ROOT/scripts/deploy/deploy_production.sh"
HEALTH="$ROOT/scripts/deploy/healthcheck.sh"

grep -Fq 'restart_required_service_instances artsfolio-email-worker' "$DEPLOY"
grep -Fq 'restart_required_service_instances artsfolio-background-worker' "$DEPLOY"
grep -Fq 'require_active_service_instances artsfolio-email-worker' "$HEALTH"
grep -Fq 'require_active_service_instances artsfolio-background-worker' "$HEALTH"

if grep -Fq 'restart_required_service artsfolio-email-worker.service' "$DEPLOY"; then
  echo 'Legacy singleton email restart remains in deploy script.' >&2
  exit 1
fi
if grep -Fq 'require_active_service artsfolio-email-worker.service' "$HEALTH"; then
  echo 'Legacy singleton email health requirement remains.' >&2
  exit 1
fi

echo 'Deploy worker instance static checks passed.'
