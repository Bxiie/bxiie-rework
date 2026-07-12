#!/bin/bash
set -euo pipefail
readonly ROOT="${ARTSFOLIO_ROOT:-/var/www/artsfolio}"
readonly STATE_DIR="${ARTSFOLIO_BACKUP_STATE_DIR:-/var/lib/artsfolio/backup-status}"
runtime_env="$(mktemp /run/artsfolio-restic-env.XXXXXX)"
chmod 0600 "$runtime_env"
trap 'rm -f "$runtime_env"' EXIT
/usr/bin/php "$ROOT/scripts/backup/export_restic_environment.php" > "$runtime_env"
set -a
source "$runtime_env"
set +a
rm -f "$runtime_env"
trap - EXIT
install -d -m 0750 -o root -g artsfolio "$STATE_DIR"
started="$(date +%s)"
output="$(mktemp)"; trap 'rm -f "$output"' EXIT
status=ok
if ! restic check --read-data-subset="${ARTSFOLIO_RESTIC_WEEKLY_READ_SUBSET:-5%}" >"$output" 2>&1; then status=failed; fi
jq -n --arg status "$status" --arg checked_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" --arg detail "$(tail -c 8000 "$output")" --argjson duration_seconds "$(( $(date +%s)-started ))" '{status:$status,checked_at:$checked_at,duration_seconds:$duration_seconds,detail:$detail}' > "$STATE_DIR/weekly.json.tmp"
mv -f "$STATE_DIR/weekly.json.tmp" "$STATE_DIR/weekly.json"
/usr/bin/php "$ROOT/scripts/ops/monitor_artsfolio.php" --force-report || true
[[ "$status" == ok ]]
# End of file.
