#!/bin/bash
set -euo pipefail

PROJECT_ROOT="${1:-/var/www/artsfolio}"
SYSTEMD_DIR="/etc/systemd/system"
BACKGROUND_INSTANCES="${ARTSFOLIO_BACKGROUND_WORKER_INSTANCES:-2}"
EMAIL_INSTANCES="${ARTSFOLIO_EMAIL_WORKER_INSTANCES:-2}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run this script with sudo." >&2
  exit 1
fi

install -m 0644 "${PROJECT_ROOT}/scripts/systemd/artsfolio-background-worker@.service" "${SYSTEMD_DIR}/artsfolio-background-worker@.service"
install -m 0644 "${PROJECT_ROOT}/scripts/systemd/artsfolio-email-worker@.service" "${SYSTEMD_DIR}/artsfolio-email-worker@.service"

systemctl daemon-reload

# Stop and disable the legacy singleton before enabling templated instances.
systemctl disable --now artsfolio-background-worker.service 2>/dev/null || true
systemctl disable --now artsfolio-email-worker.service 2>/dev/null || true

for ((i=1; i<=BACKGROUND_INSTANCES; i++)); do
  systemctl enable --now "artsfolio-background-worker@${i}.service"
done
for ((i=1; i<=EMAIL_INSTANCES; i++)); do
  systemctl enable --now "artsfolio-email-worker@${i}.service"
done

systemctl --no-pager --full status "artsfolio-background-worker@1.service" "artsfolio-email-worker@1.service" || true

echo "Installed ${BACKGROUND_INSTANCES} background and ${EMAIL_INSTANCES} email worker instance(s)."

# End of file.
