# Background Jobs

ArtsFolio runs queued jobs through `scripts/workers/run_forever.php`, normally under `artsfolio-background-worker.service` on production.

Supported tenant provisioning job types:

- `custom_domain.verify_dns`: verifies a hostname A record and updates tenant domain status.
- `tenant.domain.verify`: compatibility alias for older queued signup rows. The worker maps payload `domain` to `hostname`.
- `tenant.site.bootstrap`: finalizes tenant-site provisioning after signup by marking the tenant active and marking platform subdomains active.
- `custom_domain.render_vhost`: renders a dry-run Apache vhost artifact for custom domain workflows.
- `custom_domain.write_approved_vhost`: dry-run planning for approved vhost artifacts.

Production checks:

```bash
systemctl status artsfolio-background-worker.service --no-pager
journalctl -u artsfolio-background-worker.service -n 100 --no-pager
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php /var/www/artsfolio/scripts/workers/run_once.php
```

If jobs fail with `No handler for job type`, the worker is running but the deployed code has queued a job type that is not registered in `scripts/workers/run_once.php`.

<!-- End of file. -->
