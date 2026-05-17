#!/bin/bash
set -euo pipefail

PROJECT_ROOT="/var/www/artsfolio"
ENV_FILE="${ARTSFOLIO_ENV_FILE:-/etc/artsfolio/artsfolio.env}"
SLEEP_SECONDS="${ARTSFOLIO_EMAIL_WORKER_SLEEP_SECONDS:-10}"

cd "$PROJECT_ROOT"

while true; do
  ARTSFOLIO_ENV_FILE="$ENV_FILE" php scripts/workers/email_run_once.php || true
  sleep "$SLEEP_SECONDS"
done

# End of file.
