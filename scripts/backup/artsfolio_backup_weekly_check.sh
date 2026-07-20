#!/bin/bash
set -Eeuo pipefail

# ArtsFolio weekly Restic repository integrity check.
readonly ROOT="${ARTSFOLIO_ROOT:-/var/www/artsfolio}"
readonly STATE_DIR="${ARTSFOLIO_BACKUP_STATE_DIR:-/var/lib/artsfolio/backup-status}"
readonly CACHE_DIR="${ARTSFOLIO_RESTIC_CACHE_DIR:-/var/cache/artsfolio/restic}"
readonly LOCK_FILE="${ARTSFOLIO_BACKUP_LOCK_FILE:-/run/lock/artsfolio-backup.lock}"
readonly LOCK_WAIT_SECONDS="${ARTSFOLIO_BACKUP_LOCK_WAIT_SECONDS:-1800}"
readonly MONITOR_USER="${ARTSFOLIO_MONITOR_USER:-artsfolio}"

runtime_env=''
output=''
status_tmp=''

cleanup() {
    rm -f "${runtime_env:-}" "${output:-}" "${status_tmp:-}"
}
trap cleanup EXIT

# Serialize repository maintenance with hourly backups and restore tests. The
# weekly timer waits for a running hourly backup instead of silently skipping a
# full week of integrity coverage.
exec 9>"$LOCK_FILE"
flock -w "$LOCK_WAIT_SECONDS" 9 || {
    echo "[FAIL] Timed out waiting ${LOCK_WAIT_SECONDS}s for the ArtsFolio backup lock." >&2
    exit 75
}

runtime_env="$(mktemp /run/artsfolio-restic-env.XXXXXX)"
chmod 0600 "$runtime_env"
/usr/bin/php "$ROOT/scripts/backup/export_restic_environment.php" > "$runtime_env"
set -a
# shellcheck disable=SC1090
source "$runtime_env"
set +a
rm -f "$runtime_env"
runtime_env=''

for command in restic jq runuser flock; do
    command -v "$command" >/dev/null 2>&1 || {
        echo "[FAIL] Missing command: $command" >&2
        exit 1
    }
done

install -d -m 0750 -o root -g artsfolio "$STATE_DIR" "$CACHE_DIR"
export RESTIC_CACHE_DIR="$CACHE_DIR"

# The shared flock excludes local backup activity. Restic unlock removes only
# stale repository locks, allowing interrupted checks to recover automatically.
restic unlock

started="$(date +%s)"
output="$(mktemp /run/artsfolio-restic-check.XXXXXX)"
status_tmp="$(mktemp "$STATE_DIR/weekly.XXXXXX")"
status='ok'

if ! restic check \
    --read-data-subset="${ARTSFOLIO_RESTIC_WEEKLY_READ_SUBSET:-5%}" \
    >"$output" 2>&1
then
    status='failed'
fi

jq -n \
    --arg status "$status" \
    --arg checked_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    --arg detail "$(tail -c 8000 "$output")" \
    --argjson duration_seconds "$(( $(date +%s) - started ))" \
    '{
        status: $status,
        checked_at: $checked_at,
        duration_seconds: $duration_seconds,
        detail: $detail
    }' > "$status_tmp"

chmod 0644 "$status_tmp"
chown root:root "$status_tmp"
mv -f "$status_tmp" "$STATE_DIR/weekly.json"
status_tmp=''

# The monitor uses 0/1/2 to represent healthy/warning/critical. Values above 2
# mean the report process itself failed and must fail this scheduled job.
set +e
runuser -u "$MONITOR_USER" -- \
    /usr/bin/php "$ROOT/scripts/ops/monitor_artsfolio.php" --force-report
monitor_status=$?
set -e

if (( monitor_status > 2 )); then
    echo "[FAIL] Platform-admin health report failed with exit $monitor_status." >&2
    exit "$monitor_status"
fi

[[ "$status" == 'ok' ]]

# End of file.
