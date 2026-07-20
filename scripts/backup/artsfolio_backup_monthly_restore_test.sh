#!/bin/bash
set -Eeuo pipefail

# ArtsFolio monthly backup restore validation.
readonly ROOT="${ARTSFOLIO_ROOT:-/var/www/artsfolio}"
readonly STATE_DIR="${ARTSFOLIO_BACKUP_STATE_DIR:-/var/lib/artsfolio/backup-status}"
readonly RESTORE_DIR="${ARTSFOLIO_BACKUP_RESTORE_TEST_DIR:-/var/backups/artsfolio/restore-test}"
readonly CACHE_DIR="${ARTSFOLIO_RESTIC_CACHE_DIR:-/var/cache/artsfolio/restic}"
readonly LOCK_FILE="${ARTSFOLIO_BACKUP_LOCK_FILE:-/run/lock/artsfolio-backup.lock}"
readonly LOCK_WAIT_SECONDS="${ARTSFOLIO_BACKUP_LOCK_WAIT_SECONDS:-1800}"
readonly MONITOR_USER="${ARTSFOLIO_MONITOR_USER:-artsfolio}"

runtime_env=''
work=''
restore_log=''
status_tmp=''

cleanup() {
    rm -f "${runtime_env:-}" "${restore_log:-}" "${status_tmp:-}"
    [[ -z "${work:-}" ]] || rm -rf "$work"
}
trap cleanup EXIT

# Serialize restore validation with hourly backups and repository checks.
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

for command in restic jq gzip grep runuser flock; do
    command -v "$command" >/dev/null 2>&1 || {
        echo "[FAIL] Missing command: $command" >&2
        exit 1
    }
done

install -d -m 0750 -o root -g artsfolio \
    "$STATE_DIR" \
    "$RESTORE_DIR" \
    "$CACHE_DIR"
export RESTIC_CACHE_DIR="$CACHE_DIR"

# The shared flock excludes local backup activity. Restic unlock removes only
# stale repository locks left by interrupted jobs.
restic unlock

work="$(mktemp -d "$RESTORE_DIR/run.XXXXXX")"
restore_log="$(mktemp /run/artsfolio-restic-restore.XXXXXX.log)"
status_tmp="$(mktemp "$STATE_DIR/monthly.XXXXXX")"

started="$(date +%s)"
status='ok'
detail='Restore and SQL validation succeeded.'

if ! restic restore latest \
    --tag artsfolio-hourly \
    --target "$work" \
    >"$restore_log" 2>&1
then
    status='failed'
    detail="$(tail -c 8000 "$restore_log")"
fi

if [[ "$status" == 'ok' ]]; then
    dump="$(
        find "$work" \
            -type f \
            -name 'artsfolio-db-*.sql.gz' \
            -print |
        sort |
        tail -n 1
    )"

    if [[ -z "$dump" ]]; then
        status='failed'
        detail='No restored ArtsFolio database dump was found.'
    elif ! gzip -t "$dump"; then
        status='failed'
        detail='The restored ArtsFolio database dump failed gzip integrity validation.'
    # Do not use grep -q with pipefail here. grep -q closes the pipe after the
    # first match, causing gzip to report SIGPIPE and a false validation failure.
    elif ! gzip -dc "$dump" | grep 'CREATE TABLE' >/dev/null; then
        status='failed'
        detail='The restored ArtsFolio database dump did not contain a CREATE TABLE statement.'
    fi
fi

jq -n \
    --arg status "$status" \
    --arg checked_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    --arg detail "$detail" \
    --argjson duration_seconds "$(( $(date +%s) - started ))" \
    '{
        status: $status,
        checked_at: $checked_at,
        duration_seconds: $duration_seconds,
        detail: $detail
    }' > "$status_tmp"

chmod 0644 "$status_tmp"
chown root:root "$status_tmp"
mv -f "$status_tmp" "$STATE_DIR/monthly.json"
status_tmp=''

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
