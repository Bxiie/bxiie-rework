#!/bin/bash
set -euo pipefail
readonly ROOT="${ARTSFOLIO_ROOT:-/var/www/artsfolio}"
readonly ENV_FILE="${ARTSFOLIO_ENV_FILE:-/etc/artsfolio/artsfolio.env}"
readonly STATE_DIR="${ARTSFOLIO_BACKUP_STATE_DIR:-/var/lib/artsfolio/backup-status}"
readonly RESTORE_DIR="${ARTSFOLIO_BACKUP_RESTORE_TEST_DIR:-/var/backups/artsfolio/restore-test}"
set -a; source "$ENV_FILE"; set +a
runtime_env="$(mktemp /run/artsfolio-restic-env.XXXXXX)"
chmod 0600 "$runtime_env"
trap 'rm -f "$runtime_env"' EXIT
/usr/bin/php "$ROOT/scripts/backup/export_restic_environment.php" > "$runtime_env"
set -a; source "$runtime_env"; set +a
rm -f "$runtime_env"
trap - EXIT
install -d -m 0750 -o root -g artsfolio "$STATE_DIR" "$RESTORE_DIR"
work="$(mktemp -d "$RESTORE_DIR/run.XXXXXX")"; trap 'rm -rf "$work"' EXIT
started="$(date +%s)"; status=ok; detail='Restore and SQL validation succeeded.'
if ! restic restore latest --tag artsfolio-hourly --target "$work" >/tmp/artsfolio-restic-restore-test.log 2>&1; then status=failed; detail="$(tail -c 8000 /tmp/artsfolio-restic-restore-test.log)"; fi
if [[ "$status" == ok ]]; then
 dump="$(find "$work" -type f -name 'artsfolio-db-*.sql.gz' -print | sort | tail -1)"
 if [[ -z "$dump" ]] || ! gzip -t "$dump" || ! gzip -dc "$dump" | grep -q 'CREATE TABLE'; then status=failed; detail='Restored database dump failed gzip or SQL structure validation.'; fi
fi
jq -n --arg status "$status" --arg checked_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" --arg detail "$detail" --argjson duration_seconds "$(( $(date +%s)-started ))" '{status:$status,checked_at:$checked_at,duration_seconds:$duration_seconds,detail:$detail}' > "$STATE_DIR/monthly.json.tmp"
mv -f "$STATE_DIR/monthly.json.tmp" "$STATE_DIR/monthly.json"
/usr/bin/php "$ROOT/scripts/ops/monitor_artsfolio.php" --force-report || true
[[ "$status" == ok ]]
# End of file.
