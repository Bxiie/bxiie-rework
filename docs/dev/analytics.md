# Analytics architecture

Public requests write one minimal local `analytics_events` row through `AnalyticsRecorder`. The request path never performs external geolocation or information-schema discovery. Location comes only from trusted proxy/edge headers.

`analytics.rollup` runs through the background worker every five minutes. Rollups are rebuilt in bounded UTC buckets:

- one SQL aggregation and transaction per hour for `analytics_rollups_hourly`
- one SQL aggregation and transaction per day for `analytics_rollups_daily`

This prevents a multi-day rebuild from exhausting MariaDB temporary storage. Each bucket is deleted and replaced independently, so interrupted runs are safe to rerun.

Manual recent rebuild:

```bash
php scripts/maintenance/rebuild_analytics_rollups.php 30
php scripts/maintenance/rebuild_analytics_rollups.php --days=30
```

Manual bounded range rebuild, with an exclusive end time:

```bash
php scripts/maintenance/rebuild_analytics_rollups.php \
  --from="2026-06-01 00:00:00" \
  --to="2026-06-02 00:00:00"
```

Production MariaDB should use a disk-backed temporary directory because `/tmp` is a small RAM-backed tmpfs on the ArtsFolio server:

```ini
[mariadbd]
tmpdir=/var/lib/mysql-tmp
```

The recommended directory is `/var/lib/mysql-tmp`, owned by `mysql:mysql` with mode `0750`. The larger temporary directory is defense in depth; it does not replace bucketed application queries.

Raw events remain available for exact IP drill-down and debugging. Dashboard queries should migrate to rollups as their report shapes are stabilized.

<!-- End of file. -->
