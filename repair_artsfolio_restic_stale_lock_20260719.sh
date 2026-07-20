#!/bin/bash
set -Eeuo pipefail

# Repair ArtsFolio backup job serialization and stale Restic lock recovery.
readonly ROOT="${ARTSFOLIO_ROOT:-/var/www/artsfolio}"
readonly STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
readonly BACKUP_DIR="$ROOT/.update-backups/restic-stale-lock-$STAMP"

fail() {
    printf '[FAIL] %s\n' "$*" >&2
    exit 1
}

[[ -d "$ROOT" ]] || fail "ArtsFolio root does not exist: $ROOT"
[[ -f "$ROOT/scripts/backup/artsfolio_backup.sh" ]] || fail 'Hourly backup script is missing.'
[[ -f "$ROOT/scripts/backup/artsfolio_backup_weekly_check.sh" ]] || fail 'Weekly check script is missing.'
[[ -f "$ROOT/scripts/backup/artsfolio_backup_monthly_restore_test.sh" ]] || fail 'Monthly restore script is missing.'
command -v python3 >/dev/null 2>&1 || fail 'python3 is required.'
command -v php >/dev/null 2>&1 || fail 'php is required.'
command -v shellcheck >/dev/null 2>&1 || printf '[WARN] shellcheck is unavailable; syntax checks will still run.\n'

install -d -m 0750 "$BACKUP_DIR"
cp -a \
    "$ROOT/scripts/backup/artsfolio_backup.sh" \
    "$ROOT/scripts/backup/artsfolio_backup_weekly_check.sh" \
    "$ROOT/scripts/backup/artsfolio_backup_monthly_restore_test.sh" \
    "$ROOT/scripts/test/backup_outstanding_fixes_static.php" \
    "$ROOT/docs/dev/backup-restore-cookbook.md" \
    "$ROOT/PROJECT_STATE.md" \
    "$BACKUP_DIR/"
printf '[PASS] Backup created at %s\n' "$BACKUP_DIR"

python3 - "$ROOT" <<'PY'
from pathlib import Path
import sys

root = Path(sys.argv[1])


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text()
    if new in text:
        return
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"[FAIL] Expected one repair anchor in {path}, found {count}.")
    path.write_text(text.replace(old, new, 1))

hourly = root / "scripts/backup/artsfolio_backup.sh"
replace_once(
    hourly,
    "for command in mariadb-dump gzip restic jq flock mktemp stat; do\n",
    "for command in mariadb-dump gzip restic jq flock mktemp stat; do\n",
)
replace_once(
    hourly,
    "export RESTIC_CACHE_DIR=\"$CACHE_DIR\"\n\nstarted_epoch=\"$(date +%s)\"\n",
    "export RESTIC_CACHE_DIR=\"$CACHE_DIR\"\n\n# The process-wide flock proves no other ArtsFolio Restic job is active on this\n# host. Remove only locks Restic itself classifies as stale before continuing.\nrestic unlock\n\nstarted_epoch=\"$(date +%s)\"\n",
)

weekly = root / "scripts/backup/artsfolio_backup_weekly_check.sh"
replace_once(
    weekly,
    "readonly CACHE_DIR=\"${ARTSFOLIO_RESTIC_CACHE_DIR:-/var/cache/artsfolio/restic}\"\nreadonly MONITOR_USER=\"${ARTSFOLIO_MONITOR_USER:-artsfolio}\"\n",
    "readonly CACHE_DIR=\"${ARTSFOLIO_RESTIC_CACHE_DIR:-/var/cache/artsfolio/restic}\"\nreadonly LOCK_FILE=\"${ARTSFOLIO_BACKUP_LOCK_FILE:-/run/lock/artsfolio-backup.lock}\"\nreadonly LOCK_WAIT_SECONDS=\"${ARTSFOLIO_BACKUP_LOCK_WAIT_SECONDS:-1800}\"\nreadonly MONITOR_USER=\"${ARTSFOLIO_MONITOR_USER:-artsfolio}\"\n",
)
replace_once(
    weekly,
    "trap cleanup EXIT\n\nruntime_env=\"$(mktemp /run/artsfolio-restic-env.XXXXXX)\"\n",
    "trap cleanup EXIT\n\n# Serialize repository maintenance with hourly backups and restore tests. The\n# weekly timer waits for a running hourly backup instead of silently skipping a\n# full week of integrity coverage.\nexec 9>\"$LOCK_FILE\"\nflock -w \"$LOCK_WAIT_SECONDS\" 9 || {\n    echo \"[FAIL] Timed out waiting ${LOCK_WAIT_SECONDS}s for the ArtsFolio backup lock.\" >&2\n    exit 75\n}\n\nruntime_env=\"$(mktemp /run/artsfolio-restic-env.XXXXXX)\"\n",
)
replace_once(
    weekly,
    "for command in restic jq runuser; do\n",
    "for command in restic jq runuser flock; do\n",
)
replace_once(
    weekly,
    "export RESTIC_CACHE_DIR=\"$CACHE_DIR\"\n\nstarted=\"$(date +%s)\"\n",
    "export RESTIC_CACHE_DIR=\"$CACHE_DIR\"\n\n# The shared flock excludes local backup activity. Restic unlock removes only\n# stale repository locks, allowing interrupted checks to recover automatically.\nrestic unlock\n\nstarted=\"$(date +%s)\"\n",
)

monthly = root / "scripts/backup/artsfolio_backup_monthly_restore_test.sh"
replace_once(
    monthly,
    "readonly CACHE_DIR=\"${ARTSFOLIO_RESTIC_CACHE_DIR:-/var/cache/artsfolio/restic}\"\nreadonly MONITOR_USER=\"${ARTSFOLIO_MONITOR_USER:-artsfolio}\"\n",
    "readonly CACHE_DIR=\"${ARTSFOLIO_RESTIC_CACHE_DIR:-/var/cache/artsfolio/restic}\"\nreadonly LOCK_FILE=\"${ARTSFOLIO_BACKUP_LOCK_FILE:-/run/lock/artsfolio-backup.lock}\"\nreadonly LOCK_WAIT_SECONDS=\"${ARTSFOLIO_BACKUP_LOCK_WAIT_SECONDS:-1800}\"\nreadonly MONITOR_USER=\"${ARTSFOLIO_MONITOR_USER:-artsfolio}\"\n",
)
replace_once(
    monthly,
    "trap cleanup EXIT\n\nruntime_env=\"$(mktemp /run/artsfolio-restic-env.XXXXXX)\"\n",
    "trap cleanup EXIT\n\n# Serialize restore validation with hourly backups and repository checks.\nexec 9>\"$LOCK_FILE\"\nflock -w \"$LOCK_WAIT_SECONDS\" 9 || {\n    echo \"[FAIL] Timed out waiting ${LOCK_WAIT_SECONDS}s for the ArtsFolio backup lock.\" >&2\n    exit 75\n}\n\nruntime_env=\"$(mktemp /run/artsfolio-restic-env.XXXXXX)\"\n",
)
replace_once(
    monthly,
    "for command in restic jq gzip grep runuser; do\n",
    "for command in restic jq gzip grep runuser flock; do\n",
)
replace_once(
    monthly,
    "export RESTIC_CACHE_DIR=\"$CACHE_DIR\"\n\nwork=\"$(mktemp -d \"$RESTORE_DIR/run.XXXXXX\")\"\n",
    "export RESTIC_CACHE_DIR=\"$CACHE_DIR\"\n\n# The shared flock excludes local backup activity. Restic unlock removes only\n# stale repository locks left by interrupted jobs.\nrestic unlock\n\nwork=\"$(mktemp -d \"$RESTORE_DIR/run.XXXXXX\")\"\n",
)

test = root / "scripts/test/backup_outstanding_fixes_static.php"
replace_once(
    test,
    "foreach ([$weekly, $monthly] as $script) {\n",
    "foreach ([$hourly, $weekly, $monthly] as $script) {\n    if (!str_contains($script, 'ARTSFOLIO_BACKUP_LOCK_FILE')) {\n        $failures[] = 'Restic job does not use the shared ArtsFolio backup lock.';\n    }\n    if (!str_contains($script, 'restic unlock')) {\n        $failures[] = 'Restic job does not remove stale repository locks after serialization.';\n    }\n}\n\nforeach ([$weekly, $monthly] as $script) {\n    if (!str_contains($script, 'ARTSFOLIO_BACKUP_LOCK_WAIT_SECONDS')) {\n        $failures[] = 'Scheduled verification job does not wait for the shared backup lock.';\n    }\n",
)

doc = root / "docs/dev/backup-restore-cookbook.md"
text = doc.read_text()
marker = "## 12. Failure investigation\n"
addition = """## 12. Repository locking and stale-lock recovery\n\nAll ArtsFolio Restic jobs share `/run/lock/artsfolio-backup.lock`. Hourly backups skip when another job is active; weekly integrity checks and monthly restore tests wait up to 1,800 seconds so scheduled verification is not lost merely because an hourly snapshot overlaps.\n\nAfter obtaining the local lock, each job runs `restic unlock`. Without `--remove-all`, Restic removes only locks it classifies as stale. This repairs locks left by interrupted processes while preserving active repository locks.\n\nFor manual recovery, first prove that no Restic process is active, then load the repository environment and unlock it:\n\n```bash\nsudo pgrep -a restic || true\nsudo systemctl stop artsfolio-backup.timer artsfolio-backup-weekly-check.timer artsfolio-backup-monthly-restore.timer\ncd /var/www/artsfolio\nsudo bash -c '\n  set -Eeuo pipefail\n  runtime_env=$(mktemp /run/artsfolio-restic-env.XXXXXX)\n  trap \"rm -f $runtime_env\" EXIT\n  php scripts/backup/export_restic_environment.php >$runtime_env\n  chmod 0600 $runtime_env\n  set -a\n  source $runtime_env\n  set +a\n  export RESTIC_CACHE_DIR=/var/cache/artsfolio/restic\n  restic list locks\n  restic unlock\n  restic list locks\n'\nsudo systemctl start artsfolio-backup.timer artsfolio-backup-weekly-check.timer artsfolio-backup-monthly-restore.timer\n```\n\nDo not use `restic unlock --remove-all` unless every repository client is known to be stopped.\n\n## 13. Failure investigation\n"""
if "## 12. Repository locking and stale-lock recovery" not in text:
    if marker not in text:
        raise SystemExit(f"[FAIL] Documentation anchor missing in {doc}.")
    text = text.replace(marker, addition, 1)
    doc.write_text(text)

state = root / "PROJECT_STATE.md"
text = state.read_text()
entry = """\n## 2026-07-19 Restic repository lock serialization\n\n- Hourly backup, weekly integrity check, and monthly restore validation share `/run/lock/artsfolio-backup.lock`.\n- Weekly and monthly jobs wait up to 1,800 seconds for the shared lock; hourly backup remains nonblocking.\n- After obtaining the local lock, each job runs `restic unlock` without `--remove-all` to remove only stale repository locks left by interrupted jobs.\n- Manual recovery and verification steps are documented in `docs/dev/backup-restore-cookbook.md`.\n\n<!-- End of Restic repository lock serialization. -->\n"""
if "## 2026-07-19 Restic repository lock serialization" not in text:
    end = "# End of file."
    if end not in text:
        raise SystemExit(f"[FAIL] End marker missing in {state}.")
    text = text.replace(end, entry + "\n" + end, 1)
    state.write_text(text)
PY

chmod 0750 \
    "$ROOT/scripts/backup/artsfolio_backup.sh" \
    "$ROOT/scripts/backup/artsfolio_backup_weekly_check.sh" \
    "$ROOT/scripts/backup/artsfolio_backup_monthly_restore_test.sh"

printf '[RUN] Bash syntax checks\n'
bash -n "$ROOT/scripts/backup/artsfolio_backup.sh"
bash -n "$ROOT/scripts/backup/artsfolio_backup_weekly_check.sh"
bash -n "$ROOT/scripts/backup/artsfolio_backup_monthly_restore_test.sh"

if command -v shellcheck >/dev/null 2>&1; then
    printf '[RUN] ShellCheck\n'
    shellcheck \
        "$ROOT/scripts/backup/artsfolio_backup.sh" \
        "$ROOT/scripts/backup/artsfolio_backup_weekly_check.sh" \
        "$ROOT/scripts/backup/artsfolio_backup_monthly_restore_test.sh"
fi

printf '[RUN] PHP regression checks\n'
php -l "$ROOT/scripts/test/backup_outstanding_fixes_static.php"
php "$ROOT/scripts/test/backup_outstanding_fixes_static.php"
php "$ROOT/scripts/test/backup_operations_static.php"

printf '[PASS] Restic stale-lock repair installed.\n'
printf '[NEXT] On production, verify no Restic process is active, run a safe unlock, then start the weekly check.\n'
printf '[NEXT] Run: sudo pgrep -a restic || true\n'
printf '[NEXT] Run: sudo systemctl start artsfolio-backup-weekly-check.service\n'
printf '[NEXT] Run: sudo journalctl -u artsfolio-backup-weekly-check.service -n 120 --no-pager\n'

# End of file.
