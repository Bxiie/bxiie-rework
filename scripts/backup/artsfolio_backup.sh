#!/bin/bash
set -Eeuo pipefail

# ArtsFolio hourly encrypted off-site backup.
readonly ROOT="${ARTSFOLIO_ROOT:-/var/www/artsfolio}"
readonly ENV_FILE="${ARTSFOLIO_ENV_FILE:-/etc/artsfolio/artsfolio.env}"
readonly STATE_DIR="${ARTSFOLIO_BACKUP_STATE_DIR:-/var/lib/artsfolio/backup-status}"
readonly STAGING_DIR="${ARTSFOLIO_BACKUP_STAGING_DIR:-/var/backups/artsfolio/staging}"
readonly CACHE_DIR="${ARTSFOLIO_RESTIC_CACHE_DIR:-/var/cache/artsfolio/restic}"
readonly LOCK_FILE="${ARTSFOLIO_BACKUP_LOCK_FILE:-/run/lock/artsfolio-backup.lock}"
readonly HOST_NAME="${ARTSFOLIO_BACKUP_HOST:-$(hostname)}"

runtime_env=''
status_tmp=''
backup_json=''

cleanup() {
    rm -f "${runtime_env:-}" "${status_tmp:-}" "${backup_json:-}"
}
trap cleanup EXIT

# Prevent overlapping hourly, weekly, or manual backup runs.
exec 9>"$LOCK_FILE"
flock -n 9 || {
    echo '[INFO] Another ArtsFolio backup is active.'
    exit 0
}

[[ -r "$ENV_FILE" ]] || {
    echo "[FAIL] Missing readable configuration: $ENV_FILE" >&2
    exit 1
}

set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

runtime_env="$(mktemp /run/artsfolio-restic-env.XXXXXX)"
chmod 0600 "$runtime_env"
/usr/bin/php "$ROOT/scripts/backup/export_restic_environment.php" > "$runtime_env"
set -a
# shellcheck disable=SC1090
source "$runtime_env"
set +a
rm -f "$runtime_env"
runtime_env=''

for command in mariadb-dump gzip restic jq flock mktemp stat; do
    command -v "$command" >/dev/null 2>&1 || {
        echo "[FAIL] Missing command: $command" >&2
        exit 1
    }
done

# Restic cannot use /root/.cache under the hardened systemd unit.
install -d -m 0750 -o root -g artsfolio \
    "$STATE_DIR" \
    "$STAGING_DIR" \
    "$CACHE_DIR"
export RESTIC_CACHE_DIR="$CACHE_DIR"

# The process-wide flock proves no other ArtsFolio Restic job is active on this
# host. Remove only locks Restic itself classifies as stale before continuing.
restic unlock

started_epoch="$(date +%s)"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
dump_path="$STAGING_DIR/artsfolio-db-$stamp.sql.gz"
status_tmp="$(mktemp "$STATE_DIR/hourly.XXXXXX")"
backup_json="$(mktemp /run/artsfolio-restic-backup.XXXXXX.json)"

# Keep only the current logical dump in the stable staging directory. Using the
# directory as a top-level Restic path allows parent-snapshot matching.
find "$STAGING_DIR" -maxdepth 1 -type f -name 'artsfolio-db-*.sql.gz' -delete

mariadb-dump \
    --host="${DB_HOST:-127.0.0.1}" \
    --port="${DB_PORT:-3306}" \
    --user="$DB_USERNAME" \
    --password="$DB_PASSWORD" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE" |
    gzip -9 > "$dump_path"

dump_bytes="$(stat -c %s "$dump_path")"
storage_path="${STORAGE_PATH:-$ROOT/storage}"

# JSON output supplies the exact snapshot created by this invocation.
restic backup \
    "$STAGING_DIR" \
    "$ROOT" \
    "$storage_path" \
    /etc/artsfolio \
    /etc/caddy \
    /etc/php \
    /etc/mysql \
    /etc/systemd/system \
    /etc/fail2ban \
    /etc/ufw \
    /etc/ssh/sshd_config \
    /etc/ssh/sshd_config.d \
    --exclude "$ROOT/.update-backups" \
    --exclude "$ROOT/.patch-backups" \
    --exclude "$ROOT/storage/cache" \
    --tag artsfolio-hourly \
    --host "$HOST_NAME" \
    --json |
    tee "$backup_json"

snapshot_id="$(
    jq -r '
        select(.message_type == "summary")
        | .snapshot_id // empty
    ' "$backup_json" |
    tail -n 1
)"

[[ -n "$snapshot_id" ]] || {
    echo '[FAIL] Restic completed without returning a snapshot ID.' >&2
    exit 1
}

snapshot_short="${snapshot_id:0:8}"
repo_bytes="$(
    restic stats \
        --mode raw-data \
        --json \
        --tag artsfolio-hourly |
    jq -r '.total_size // 0'
)"
finished_epoch="$(date +%s)"

jq -n \
    --arg status 'ok' \
    --arg checked_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    --arg snapshot_id "$snapshot_short" \
    --arg snapshot_id_full "$snapshot_id" \
    --arg dump_path "$dump_path" \
    --argjson dump_bytes "$dump_bytes" \
    --argjson repository_bytes "$repo_bytes" \
    --argjson duration_seconds "$((finished_epoch - started_epoch))" \
    '{
        status: $status,
        checked_at: $checked_at,
        snapshot_id: $snapshot_id,
        snapshot_id_full: $snapshot_id_full,
        dump_path: $dump_path,
        dump_bytes: $dump_bytes,
        repository_bytes: $repository_bytes,
        duration_seconds: $duration_seconds
    }' > "$status_tmp"

chmod 0644 "$status_tmp"
chown root:root "$status_tmp"
mv -f "$status_tmp" "$STATE_DIR/hourly.json"
status_tmp=''

echo "[PASS] Restic snapshot $snapshot_short completed."

# End of file.
