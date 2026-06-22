# Analytics operations

The background jobs page should show a recurring `analytics.rollup` job. Normal execution is every five minutes. Review execution duration and errors at `/platform/admin/jobs`.

## MariaDB temporary storage

The ArtsFolio host mounts `/tmp` as an approximately 1 GB tmpfs. Configure MariaDB to use disk-backed temporary storage with the source-controlled helper:

```bash
cd /var/www/artsfolio
sudo ./scripts/ops/configure_mariadb_tmpdir.sh
sudo systemctl status mariadb --no-pager
```

The helper creates `/var/lib/mysql-tmp`, writes `/etc/mysql/mariadb.conf.d/60-artsfolio-tmpdir.cnf`, validates MariaDB configuration, restarts MariaDB, and verifies the effective `tmpdir`.

Verify:

```bash
mysql \
  -h "$DB_HOST" \
  -P "${DB_PORT:-3306}" \
  -u "$DB_USERNAME" \
  -p"$DB_PASSWORD" \
  "$DB_DATABASE" \
  -e "SHOW VARIABLES LIKE 'tmpdir';"
```

## Manual rollup recovery

The rebuild is resumable and processes one UTC hour and one UTC day per transaction:

```bash
cd /var/www/artsfolio
php scripts/maintenance/rebuild_analytics_rollups.php --days=1
php scripts/maintenance/rebuild_analytics_rollups.php --days=7
php scripts/maintenance/rebuild_analytics_rollups.php --days=30
```

A precise range may be rebuilt when diagnosing a dense fixture interval:

```bash
php scripts/maintenance/rebuild_analytics_rollups.php \
  --from="2026-06-20 00:00:00" \
  --to="2026-06-21 00:00:00"
```

After a successful manual rebuild:

```bash
sudo systemctl start artsfolio-background-worker.service
sudo systemctl status artsfolio-background-worker.service --no-pager
journalctl -u artsfolio-background-worker.service -n 100 --no-pager
```

Local HTTP smoke probes are marked and no longer add expected missing-token denials to the audit log. External missing-token requests remain audited.

<!-- End of file. -->
