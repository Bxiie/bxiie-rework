#!/bin/bash
set -euo pipefail

PROJECT_ROOT="/var/www/artsfolio"
ENV_FILE="/etc/artsfolio/artsfolio.env"

echo "== ArtsFolio production deploy =="

cd "$PROJECT_ROOT"

echo
echo "== Git status =="
git status --short

echo
echo "== Fetch latest =="
git fetch origin

CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"

echo
echo "== Pull latest on branch: $CURRENT_BRANCH =="
git pull --ff-only origin "$CURRENT_BRANCH"

echo
echo "== Verify env file =="
test -f "$ENV_FILE"

echo
echo "== PHP syntax checks =="
find app public scripts config -name '*.php' -print0 | xargs -0 -n1 php -l > /tmp/artsfolio-php-lint.log
tail -n 5 /tmp/artsfolio-php-lint.log

echo
echo "== Run migrations =="
ARTSFOLIO_ENV_FILE="$ENV_FILE" php scripts/database/migrate.php

echo
echo "== Migration integrity =="
ARTSFOLIO_ENV_FILE="$ENV_FILE" php scripts/database/check_migration_integrity.php

echo
echo "== Preflight =="
ARTSFOLIO_ENV_FILE="$ENV_FILE" ./scripts/test/preflight.sh

echo
echo "== Restart services =="
sudo systemctl restart php8.4-fpm
sudo systemctl restart caddy
sudo systemctl restart artsfolio-email-worker.service
if systemctl list-unit-files | grep -q "^artsfolio-background-worker.service"; then
  sudo systemctl restart artsfolio-background-worker.service
else
  echo "WARNING: artsfolio-background-worker.service is not installed. Queued background_jobs will not execute until it is installed." >&2
fi

echo
echo "== Health check =="
ARTSFOLIO_ENV_FILE="$ENV_FILE" ./scripts/deploy/healthcheck.sh

echo
echo "== Deploy complete =="

# End of file.
