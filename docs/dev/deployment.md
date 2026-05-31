# ArtsFolio Developer Deployment Notes

Production deployments are run from `/var/www/artsfolio` with `scripts/deploy/deploy_production.sh`, usually through the `deploy_to_production` shell alias.

The deploy script is intentionally fail-fast. It must end with either `== DEPLOY SUCCEEDED ==` or `== DEPLOY FAILED ==`. A missing or inactive `artsfolio-background-worker.service` is a deploy failure because background jobs, DNS verification, and worker heartbeat checks depend on that unit.

Required production services include:

- `php8.4-fpm`
- `caddy`
- `artsfolio-email-worker.service`
- `artsfolio-background-worker.service`

Useful checks:

```bash
systemctl cat artsfolio-background-worker.service
systemctl is-active artsfolio-background-worker.service
journalctl -u artsfolio-background-worker.service -n 100 --no-pager
```

The deploy script checks the worker with `systemctl cat` rather than parsing `systemctl list-unit-files`, because list output is formatting-sensitive and previously produced false missing-worker warnings.

# End of file.
