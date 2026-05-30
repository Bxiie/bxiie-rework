# ArtsFolio Background Workers

Background jobs are stored in `background_jobs`. The admin page at `/platform/admin/jobs` only displays and mutates records. Jobs execute only when the production background worker is installed and running.

## Production unit

Install the worker once on the production server:

```bash
cd /var/www/artsfolio
sudo cp scripts/systemd/artsfolio-background-worker.service /etc/systemd/system/artsfolio-background-worker.service
sudo systemctl daemon-reload
sudo systemctl enable --now artsfolio-background-worker.service
systemctl status artsfolio-background-worker.service
```

## Run one job manually

```bash
cd /var/www/artsfolio
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php scripts/workers/run_once.php
```

## Troubleshooting queued jobs

```bash
systemctl status artsfolio-background-worker.service
journalctl -u artsfolio-background-worker.service -n 100 --no-pager
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php scripts/test/job_status.php
```

# End of file.
