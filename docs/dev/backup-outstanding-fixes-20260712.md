## July 2026 backup reliability repair

The backup jobs use `/var/cache/artsfolio/restic` rather than `/root/.cache`.
The hourly job backs up the stable staging directory and captures the snapshot
identifier directly from `restic backup --json`. This allows parent-snapshot
matching and prevents stale snapshot identifiers in monitoring data.

Weekly and monthly jobs invoke the operations monitor as the `artsfolio` Unix
account. Monitor exit codes 0, 1, and 2 represent healthy, warning, and critical
system state; values above 2 mean the report process itself failed.

The monthly SQL check deliberately avoids `grep -q` in a `pipefail` pipeline.
The complete decompressed stream is consumed so gzip does not receive SIGPIPE.

Production verification:

```bash
sudo systemctl daemon-reload
sudo systemctl restart artsfolio-backup.timer
sudo systemctl start artsfolio-backup.service
sudo systemctl start artsfolio-backup-weekly-check.service
sudo systemctl start artsfolio-backup-monthly-restore.service
sudo jq . /var/lib/artsfolio/backup-status/hourly.json
sudo jq . /var/lib/artsfolio/backup-status/weekly.json
sudo jq . /var/lib/artsfolio/backup-status/monthly.json
```

<!-- End of July 2026 backup reliability repair. -->
