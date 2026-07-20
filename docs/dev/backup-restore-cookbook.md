# ArtsFolio backup and restore cookbook

## Purpose

ArtsFolio uses Restic to create encrypted, deduplicated hourly snapshots in Backblaze B2. Weekly repository checks and monthly restore tests write status files consumed by the System Operations monitor. Each weekly and monthly job forces a platform-admin health email after recording its result.

## Protected data

- MariaDB logical dump created with `--single-transaction`, routines, triggers, events, and binary-safe output.
- `/var/www/artsfolio`, including application code, migrations, documentation, and deployment scripts.
- The configured `STORAGE_PATH`, including original artwork and site images.
- `/etc/artsfolio`, Caddy, PHP, MariaDB, systemd, fail2ban, UFW, and selected SSH configuration.

The live MariaDB data directory is intentionally not copied. The consistent logical dump is the supported database recovery artifact.

## 1. Create Backblaze B2 resources

1. Create a private bucket dedicated to ArtsFolio backups.
2. Enable object lock only after validating the retention and prune behavior in a test bucket.
3. Create a bucket-scoped application key with list, read, write, and delete capabilities.
4. Store the Restic repository password in the password manager used for disaster recovery.

## 2. Install packages

```bash
sudo apt-get update
sudo apt-get install -y restic jq mariadb-client
```

## 3. Configure credentials

Create `/etc/artsfolio/backup.env`:

```bash
sudo install -m 0600 -o root -g root /dev/null /etc/artsfolio/backup.env
sudoedit /etc/artsfolio/backup.env
```

Use this content, replacing the values:

```bash
RESTIC_REPOSITORY=b2:YOUR_BUCKET_NAME:artsfolio-production
RESTIC_PASSWORD=GENERATE_A_LONG_RANDOM_PASSWORD
B2_ACCOUNT_ID=YOUR_BUCKET_SCOPED_KEY_ID
B2_ACCOUNT_KEY=YOUR_BUCKET_SCOPED_APPLICATION_KEY
ARTSFOLIO_RESTIC_WEEKLY_READ_SUBSET=5%
```

Do not place these secrets in `PROJECT_STATE.md` or Git.

## 4. Initialize the repository

```bash
sudo set -a
sudo source /etc/artsfolio/backup.env
sudo set +a
sudo restic init
sudo restic snapshots
```

`restic init` must be run exactly once for a new repository. An “already initialized” response is safe when reconnecting to an existing repository.

## 5. Install systemd units

```bash
cd /var/www/artsfolio
sudo install -m 0644 scripts/systemd/artsfolio-backup.service /etc/systemd/system/
sudo install -m 0644 scripts/systemd/artsfolio-backup.timer /etc/systemd/system/
sudo install -m 0644 scripts/systemd/artsfolio-backup-weekly-check.service /etc/systemd/system/
sudo install -m 0644 scripts/systemd/artsfolio-backup-weekly-check.timer /etc/systemd/system/
sudo install -m 0644 scripts/systemd/artsfolio-backup-monthly-restore.service /etc/systemd/system/
sudo install -m 0644 scripts/systemd/artsfolio-backup-monthly-restore.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now artsfolio-backup.timer artsfolio-backup-weekly-check.timer artsfolio-backup-monthly-restore.timer
```

Schedules:

- Hourly snapshots, with up to five minutes of randomized delay.
- Weekly repository check on Sunday at 4:20 AM, with up to ten minutes of randomized delay.
- Monthly restore test on the first day of the month at 5:20 AM, with up to ten minutes of randomized delay.

## 6. Run the first backup manually

```bash
sudo systemctl start artsfolio-backup.service
sudo systemctl status artsfolio-backup.service --no-pager
sudo journalctl -u artsfolio-backup.service -n 100 --no-pager
sudo cat /var/lib/artsfolio/backup-status/hourly.json | jq .
```

Then run the operations monitor so the new backup metrics appear immediately:

```bash
cd /var/www/artsfolio
sudo -u artsfolio /usr/bin/php scripts/ops/monitor_artsfolio.php --force-report
```

Open **Platform Admin → System Operations** and confirm the `backup.*` metrics are present.

## 7. Validate weekly and monthly notifications

```bash
sudo systemctl start artsfolio-backup-weekly-check.service
sudo systemctl status artsfolio-backup-weekly-check.service --no-pager
sudo cat /var/lib/artsfolio/backup-status/weekly.json | jq .

sudo systemctl start artsfolio-backup-monthly-restore.service
sudo systemctl status artsfolio-backup-monthly-restore.service --no-pager
sudo cat /var/lib/artsfolio/backup-status/monthly.json | jq .
```

Each job runs the existing monitor with `--force-report`. The report is sent directly through configured platform email to all active platform owners and administrators. It includes the backup result plus all other system metrics.

## 8. Apply retention daily

Add a separate daily forget/prune job only after the first successful restore test. Recommended retention:

```bash
restic forget --host "$(hostname)" --tag artsfolio-hourly \
  --keep-hourly 48 --keep-daily 14 --keep-weekly 8 \
  --keep-monthly 12 --keep-yearly 3 --prune
```

Do not run prune hourly. Do not combine immutable object-lock retention with pruning until the interaction has been tested against a non-production bucket.

## 9. Restore a single file

```bash
sudo set -a
sudo source /etc/artsfolio/backup.env
sudo set +a
sudo mkdir -p /var/backups/artsfolio/manual-restore
sudo restic restore latest --target /var/backups/artsfolio/manual-restore \
  --include /var/www/artsfolio/storage/uploads/PATH/TO/FILE
```

Inspect and copy the restored file into place. Do not overwrite production blindly.

## 10. Restore the database

```bash
sudo restic restore latest --target /var/backups/artsfolio/manual-restore \
  --include '/var/backups/artsfolio/staging/artsfolio-db-*.sql.gz'
find /var/backups/artsfolio/manual-restore -name 'artsfolio-db-*.sql.gz' -print
```

Validate before importing:

```bash
gzip -t /path/to/artsfolio-db-TIMESTAMP.sql.gz
gzip -dc /path/to/artsfolio-db-TIMESTAMP.sql.gz | head -50
```

Import into a temporary database first. Production replacement is destructive and requires a maintenance window:

```bash
mysql -u root -p -e 'CREATE DATABASE artsfolio_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
gzip -dc /path/to/artsfolio-db-TIMESTAMP.sql.gz | mysql -u root -p artsfolio_restore_test
mysql -u root -p artsfolio_restore_test -e 'SHOW TABLES; SELECT COUNT(*) FROM tenants; SELECT COUNT(*) FROM artworks;'
```

## 11. Monitoring metrics

The System Operations dashboard and health emails include:

- `backup.snapshot.age_minutes`
- `backup.snapshot.status`
- `backup.snapshot.duration`
- `backup.repository.size`
- `backup.database_dump.size`
- `backup.integrity_check.age_hours`
- `backup.integrity_check.status`
- `backup.restore_test.age_days`
- `backup.restore_test.status`
- State of all three backup timers

An hourly snapshot older than 90 minutes warns and older than 180 minutes is critical. Weekly and monthly checks also become stale when their expected windows are missed.

## 12. Repository locking and stale-lock recovery

All ArtsFolio Restic jobs share `/run/lock/artsfolio-backup.lock`. Hourly backups skip when another job is active; weekly integrity checks and monthly restore tests wait up to 1,800 seconds so scheduled verification is not lost merely because an hourly snapshot overlaps.

After obtaining the local lock, each job runs `restic unlock`. Without `--remove-all`, Restic removes only locks it classifies as stale. This repairs locks left by interrupted processes while preserving active repository locks.

For manual recovery, first prove that no Restic process is active, then load the repository environment and unlock it:

```bash
sudo pgrep -a restic || true
sudo systemctl stop artsfolio-backup.timer artsfolio-backup-weekly-check.timer artsfolio-backup-monthly-restore.timer
cd /var/www/artsfolio
sudo bash -c '
  set -Eeuo pipefail
  runtime_env=$(mktemp /run/artsfolio-restic-env.XXXXXX)
  trap "rm -f $runtime_env" EXIT
  php scripts/backup/export_restic_environment.php >$runtime_env
  chmod 0600 $runtime_env
  set -a
  source $runtime_env
  set +a
  export RESTIC_CACHE_DIR=/var/cache/artsfolio/restic
  restic list locks
  restic unlock
  restic list locks
'
sudo systemctl start artsfolio-backup.timer artsfolio-backup-weekly-check.timer artsfolio-backup-monthly-restore.timer
```

Do not use `restic unlock --remove-all` unless every repository client is known to be stopped.

## 13. Failure investigation

```bash
systemctl list-timers 'artsfolio-backup*' --all
systemctl status artsfolio-backup.service --no-pager
journalctl -u artsfolio-backup.service --since '2 hours ago' --no-pager
journalctl -u artsfolio-backup-weekly-check.service --since '14 days ago' --no-pager
journalctl -u artsfolio-backup-monthly-restore.service --since '60 days ago' --no-pager
sudo -E restic snapshots
sudo -E restic check
```

A failed weekly or monthly job still records a failed status before returning nonzero, so the operations monitor and email report preserve the failure signal.

# End of file.

## Platform Admin backup configuration

Restic and Backblaze values are managed under **Platform Admin → Platform Settings → Off-site backups**. The backup jobs no longer read `/etc/artsfolio/backup.env`.

Configure:

- Restic repository, for example `b2:bucket-name:artsfolio-production`
- Restic repository password
- Backblaze B2 account ID
- Backblaze B2 application key
- Weekly repository read subset, normally `5%`

Secret fields are write-only in the browser. A blank secret field preserves its current value. The PHP exporter at `scripts/backup/export_restic_environment.php` reads settings through the application database connection and creates shell exports only on standard output. Backup scripts redirect that output into a mode `0600` temporary file under `/run`, source it, and immediately remove it.

The platform database now contains backup credentials. Database dumps and access to Platform Settings must therefore remain restricted. Keep a second copy of the Restic password in an external password manager because the repository cannot be decrypted without it.

After saving settings, verify without printing secrets:

```bash
cd /var/www/artsfolio
sudo php scripts/backup/export_restic_environment.php >/run/artsfolio-restic-test.env
sudo test -s /run/artsfolio-restic-test.env
sudo chmod 0600 /run/artsfolio-restic-test.env
sudo rm -f /run/artsfolio-restic-test.env
```

Then initialize or inspect the repository through a protected temporary environment:

```bash
cd /var/www/artsfolio
sudo bash -c '
set -euo pipefail
runtime_env="$(mktemp /run/artsfolio-restic-env.XXXXXX)"
trap "rm -f \"$runtime_env\"" EXIT
chmod 0600 "$runtime_env"
php scripts/backup/export_restic_environment.php > "$runtime_env"
set -a
source "$runtime_env"
set +a
restic snapshots
'
```

<!-- End of Platform Admin backup configuration. -->
