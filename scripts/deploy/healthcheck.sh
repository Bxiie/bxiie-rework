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

require_active_service_instances() {
  local template_name="$1"
  local -a service_names=()

  mapfile -t service_names < <(
    systemctl list-units \
      --type=service \
      --all \
      --plain \
      --no-legend \
      "${template_name}@*.service" \
      | awk '{print $1}' \
      | sort -u
  )

  if [ "${#service_names[@]}" -eq 0 ]; then
    echo "ERROR: no installed systemd instances found for ${template_name}@.service" >&2
    exit 1
  fi

  local service_name
  for service_name in "${service_names[@]}"; do
    require_active_service "$service_name"
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
require_active_service_instances artsfolio-email-worker
require_active_service_instances artsfolio-background-worker

echo "Service checks passed."

echo
echo "== Database integrity =="

ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php scripts/database/check_migration_integrity.php

echo
echo "== Healthcheck passed =="

# End of file.
