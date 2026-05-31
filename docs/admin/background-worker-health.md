# Background worker health

Platform admin pages now read `worker_heartbeats` and show an error banner when no background worker heartbeat has been seen within 75 seconds. This catches a stopped or wedged `artsfolio-background-worker.service` before queued jobs silently pile up.

Operational checks:

```bash
systemctl status artsfolio-background-worker.service --no-pager
journalctl -u artsfolio-background-worker.service -n 100 --no-pager
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php /var/www/artsfolio/scripts/workers/run_once.php
```

The heartbeat repository stores heartbeat timestamps with `UTC_TIMESTAMP()` and evaluates them as UTC. This avoids false stale reports caused by MariaDB and PHP using different local timezone assumptions.

# End of file.
