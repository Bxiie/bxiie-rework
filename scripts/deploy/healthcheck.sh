#!/bin/bash
set -euo pipefail

echo "== HTTP checks =="

curl -fsS https://artsfol.io/ > /dev/null
curl -fsS https://artsfol.io/login > /dev/null
curl -fsS https://bxiie.com/ > /dev/null
curl -fsS https://bxiie.com/login > /dev/null

echo "HTTP checks passed."

echo
echo "== Service checks =="

systemctl is-active --quiet caddy
systemctl is-active --quiet php8.4-fpm
systemctl is-active --quiet mariadb
systemctl is-active --quiet artsfolio-email-worker.service
if systemctl list-unit-files | grep -q "^artsfolio-background-worker.service"; then
  systemctl is-active --quiet artsfolio-background-worker.service
else
  echo "WARNING: artsfolio-background-worker.service is not installed. Background jobs may remain queued." >&2
fi

echo "Service checks passed."

echo
echo "== Database integrity =="

ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php scripts/database/check_migration_integrity.php

echo
echo "== Healthcheck passed =="

# End of file.
