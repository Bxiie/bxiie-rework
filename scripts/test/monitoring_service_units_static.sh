#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
grep -q 'OnCalendar=\*-\*-\* \*:0/5:00' "${ROOT}/scripts/systemd/artsfolio-monitor.timer"
grep -q 'Persistent=true' "${ROOT}/scripts/systemd/artsfolio-monitor.timer"
grep -q 'monitor_artsfolio.php' "${ROOT}/scripts/systemd/artsfolio-monitor.service"
grep -q 'enable --now artsfolio-monitor.timer' "${ROOT}/scripts/ops/install_monitoring_service.sh"
echo "Monitoring service unit static checks passed."
