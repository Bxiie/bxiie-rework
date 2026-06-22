#!/bin/bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SYSTEMD_DIR="/etc/systemd/system"

if [ "$(id -u)" -ne 0 ]; then
  echo "Run this installer with sudo." >&2
  exit 1
fi

install -m 0644 "${PROJECT_ROOT}/scripts/systemd/artsfolio-monitor.service" "${SYSTEMD_DIR}/artsfolio-monitor.service"
install -m 0644 "${PROJECT_ROOT}/scripts/systemd/artsfolio-monitor.timer" "${SYSTEMD_DIR}/artsfolio-monitor.timer"

systemctl daemon-reload
systemctl enable --now artsfolio-monitor.timer
systemctl start artsfolio-monitor.service

systemctl status artsfolio-monitor.timer --no-pager
systemctl status artsfolio-monitor.service --no-pager || true
