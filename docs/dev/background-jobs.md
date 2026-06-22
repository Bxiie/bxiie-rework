# Background jobs and email workers

ArtsFolio uses MariaDB-backed queues for background jobs and outbound email. Phase 4 supports multiple worker instances safely.

## Claiming

Both queues claim with `SELECT ... FOR UPDATE SKIP LOCKED` inside a transaction, followed by a guarded status update. Separate workers therefore skip rows already locked by another worker.

## Stale recovery

Before each claim, background workers requeue `running` jobs older than `ARTSFOLIO_BACKGROUND_STALE_MINUTES` and email workers requeue `sending` rows older than `ARTSFOLIO_EMAIL_STALE_MINUTES`. Defaults are 30 minutes. Recovery appends a diagnostic to `last_error`.

## Services

Install two instances of each worker with:

```bash
cd /var/www/artsfolio
sudo ./scripts/ops/install_worker_services.sh
```

Override counts with `ARTSFOLIO_BACKGROUND_WORKER_INSTANCES` and `ARTSFOLIO_EMAIL_WORKER_INSTANCES`. The installer disables the legacy singleton background unit before enabling templated instances.

## Verification

```bash
systemctl status 'artsfolio-background-worker@*.service' --no-pager
systemctl status 'artsfolio-email-worker@*.service' --no-pager
journalctl -u 'artsfolio-background-worker@*.service' -n 100 --no-pager
journalctl -u 'artsfolio-email-worker@*.service' -n 100 --no-pager
```

The platform Jobs page displays queue totals, oldest queued age, and fresh worker count.

# End of file.
