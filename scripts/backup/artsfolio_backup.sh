#!/bin/bash
set -euo pipefail

readonly ROOT="${ARTSFOLIO_ROOT:-/var/www/artsfolio}"
readonly ENV_FILE="${ARTSFOLIO_ENV_FILE:-/etc/artsfolio/artsfolio.env}"
readonly STATE_DIR="${ARTSFOLIO_BACKUP_STATE_DIR:-/var/lib/artsfolio/backup-status}"
readonly STAGING_DIR="${ARTSFOLIO_BACKUP_STAGING_DIR:-/var/backups/artsfolio/staging}"
readonly LOCK_FILE="${ARTSFOLIO_BACKUP_LOCK_FILE:-/run/lock/artsfolio-backup.lock}"

exec 9>"$LOCK_FILE"
flock -n 9 || { echo '[INFO] Another backup is active.'; exit 0; }

for file in "$ENV_FILE"; do
    [[ -r "$file" ]] || { echo "[FAIL] Missing readable configuration: $file" >&2; exit 1; }
done

set -a
source "$ENV_FILE"
set +a

runtime_env="$(mktemp /run/artsfolio-restic-env.XXXXXX)"
chmod 0600 "$runtime_env"
trap 'rm -f "$runtime_env"' EXIT
/usr/bin/php "$ROOT/scripts/backup/export_restic_environment.php" > "$runtime_env"
set -a
source "$runtime_env"
set +a
rm -f "$runtime_env"
trap - EXIT

for command in mariadb-dump gzip restic jq; do
    command -v "$command" >/dev/null || { echo "[FAIL] Missing command: $command" >&2; exit 1; }
done

install -d -m 0750 -o root -g artsfolio "$STATE_DIR" "$STAGING_DIR"
started_epoch="$(date +%s)"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
dump_path="$STAGING_DIR/artsfolio-db-$stamp.sql.gz"
status_tmp="$(mktemp "$STATE_DIR/hourly.XXXXXX")"
trap 'rm -f "$status_tmp"' EXIT

mariadb-dump \
    --host="${DB_HOST:-127.0.0.1}" \
    --port="${DB_PORT:-3306}" \
    --user="$DB_USERNAME" \
    --password="$DB_PASSWORD" \
    --single-transaction --quick --routines --triggers --events --hex-blob \
    --default-character-set=utf8mb4 "$DB_DATABASE" | gzip -9 > "$dump_path"

dump_bytes="$(stat -c %s "$dump_path")"
storage_path="${STORAGE_PATH:-$ROOT/storage}"
restic backup \
    "$dump_path" "$ROOT" "$storage_path" \
    /etc/artsfolio /etc/caddy /etc/php /etc/mysql /etc/systemd/system /etc/fail2ban /etc/ufw /etc/ssh/sshd_config /etc/ssh/sshd_config.d \
    --exclude "$ROOT/.update-backups" --exclude "$ROOT/.patch-backups" \
    --exclude "$ROOT/storage/cache" --tag artsfolio-hourly --host "$(hostname)"

snapshot_id="$(restic snapshots --latest 1 --json | jq -r '.[0].short_id // .[0].id // "unknown"')"
repo_bytes="$(restic stats --mode raw-data --json | jq -r '.total_size // 0')"
finished_epoch="$(date +%s)"

jq -n \
  --arg status ok --arg checked_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --arg snapshot_id "$snapshot_id" --arg dump_path "$dump_path" \
  --argjson dump_bytes "$dump_bytes" --argjson repository_bytes "$repo_bytes" \
  --argjson duration_seconds "$((finished_epoch-started_epoch))" \
  '{status:$status,checked_at:$checked_at,snapshot_id:$snapshot_id,dump_path:$dump_path,dump_bytes:$dump_bytes,repository_bytes:$repository_bytes,duration_seconds:$duration_seconds}' > "$status_tmp"
mv -f "$status_tmp" "$STATE_DIR/hourly.json"
find "$STAGING_DIR" -type f -name 'artsfolio-db-*.sql.gz' -mtime +2 -delete

echo "[PASS] Restic snapshot $snapshot_id completed."
# End of file.
