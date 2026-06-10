#!/bin/bash
# Deploy the ArtsFolio production checkout and clearly report final status.

set -euo pipefail

PROJECT_ROOT="/var/www/artsfolio"
ENV_FILE="/etc/artsfolio/artsfolio.env"
DEPLOY_STARTED_AT="$(date -Is)"
DEPLOY_STAGE="initializing"

finish_deploy() {
  local exit_code="$?"
  local finished_at
  finished_at="$(date -Is)"

  echo
  if [ "$exit_code" -eq 0 ]; then
    echo "== DEPLOY SUCCEEDED =="
    echo "Started:  $DEPLOY_STARTED_AT"
    echo "Finished: $finished_at"
    echo "Project:  $PROJECT_ROOT"
    echo "Branch:   $(git -C "$PROJECT_ROOT" rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'unknown')"
    echo "Commit:   $(git -C "$PROJECT_ROOT" rev-parse --short HEAD 2>/dev/null || echo 'unknown')"
  else
    echo "== DEPLOY FAILED ==" >&2
    echo "Started:      $DEPLOY_STARTED_AT" >&2
    echo "Failed:       $finished_at" >&2
    echo "Failed stage: $DEPLOY_STAGE" >&2
    echo "Exit code:    $exit_code" >&2
    echo "Project:      $PROJECT_ROOT" >&2
    echo "Branch:       $(git -C "$PROJECT_ROOT" rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'unknown')" >&2
    echo "Commit:       $(git -C "$PROJECT_ROOT" rev-parse --short HEAD 2>/dev/null || echo 'unknown')" >&2
    echo "Next step: rerun the failed command or inspect the output immediately above this banner." >&2
  fi
}

handle_interrupt() {
  echo >&2
  echo "Deploy interrupted." >&2
  exit 130
}

trap finish_deploy EXIT
trap handle_interrupt INT TERM

section() {
  DEPLOY_STAGE="$1"
  echo
  echo "== $1 =="
}

restart_required_service() {
  local service_name="$1"

  if ! systemctl cat "$service_name" >/dev/null 2>&1; then
    echo "ERROR: required systemd service is not installed: $service_name" >&2
    exit 1
  fi

  sudo systemctl restart "$service_name"
  sudo systemctl is-active --quiet "$service_name"
}

echo "== ArtsFolio production deploy =="

cd "$PROJECT_ROOT"

section "Git status"
git status --short

section "Fetch latest"
git fetch origin

CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"

section "Pull latest on branch: $CURRENT_BRANCH"
git pull --ff-only origin "$CURRENT_BRANCH"

section "Verify env file"
test -f "$ENV_FILE"

section "PHP syntax checks"
find app public scripts config -name '*.php' -print0 | xargs -0 -n1 php -l > /tmp/artsfolio-php-lint.log
tail -n 5 /tmp/artsfolio-php-lint.log

section "Run migrations"
ARTSFOLIO_ENV_FILE="$ENV_FILE" php scripts/database/migrate.php

section "Migration integrity"
ARTSFOLIO_ENV_FILE="$ENV_FILE" php scripts/database/check_migration_integrity.php

section "Preflight"
ARTSFOLIO_ENV_FILE="$ENV_FILE" ./scripts/test/preflight.sh

section "Restart services"
restart_required_service php8.4-fpm
restart_required_service caddy
restart_required_service artsfolio-email-worker.service
restart_required_service artsfolio-background-worker.service

section "Health check"
ARTSFOLIO_ENV_FILE="$ENV_FILE" ./scripts/deploy/healthcheck.sh

DEPLOY_STAGE="complete"

# End of file.
