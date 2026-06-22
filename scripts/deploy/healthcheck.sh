#!/bin/bash
# Verify the ArtsFolio production web, service, and database health.

set -euo pipefail

require_active_service() {
  local service_name="$1"

  if ! systemctl cat "$service_name" >/dev/null 2>&1; then
    echo "ERROR: required systemd service is not installed: $service_name" >&2
    exit 1
  fi

  if ! systemctl is-active --quiet "$service_name"; then
    echo "ERROR: required systemd service is not active: $service_name" >&2
    systemctl status "$service_name" --no-pager >&2 || true
    exit 1
  fi
}


require_active_instances() {
  local pattern="$1"
  local label="$2"
  mapfile -t units < <(systemctl list-units --type=service --all "$pattern" --no-legend 2>/dev/null | awk '{print $1}' | sort -u)

  if [ "${#units[@]}" -eq 0 ]; then
    echo "ERROR: no ${label} worker instances found for ${pattern}" >&2
    exit 1
  fi

  for unit in "${units[@]}"; do
    require_active_service "$unit"
  done
}

echo "== HTTP checks =="

curl -fsS https://artsfol.io/ > /dev/null
curl -fsS https://artsfol.io/login > /dev/null
curl -fsS https://bxiie.com/ > /dev/null
curl -fsS https://bxiie.com/login > /dev/null

echo "HTTP checks passed."

echo
echo "== Service checks =="

require_active_service caddy
require_active_service php8.4-fpm
require_active_service mariadb
require_active_instances "artsfolio-email-worker@*.service" "email"
require_active_instances "artsfolio-background-worker@*.service" "background"
if systemctl cat artsfolio-monitor.timer >/dev/null 2>&1; then
  require_active_service artsfolio-monitor.timer
fi

echo "Service checks passed."

echo
echo "== Database integrity =="

ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php scripts/database/check_migration_integrity.php

echo
echo "== Healthcheck passed =="

# End of file.
