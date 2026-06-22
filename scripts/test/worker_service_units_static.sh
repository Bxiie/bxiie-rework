#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
for unit in artsfolio-background-worker@.service artsfolio-email-worker@.service; do
  grep -q '^User=www-data$' "$ROOT/scripts/systemd/$unit"
  grep -q '^Restart=always$' "$ROOT/scripts/systemd/$unit"
done
bash -n "$ROOT/scripts/ops/install_worker_services.sh"
bash -n "$ROOT/scripts/workers/email_loop.sh"
echo "Worker service unit static checks passed."

# End of file.
