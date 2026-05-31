# ArtsFolio Production Deployment

Production deploys are run from `/var/www/artsfolio` with `scripts/deploy/deploy_production.sh`.

The deploy script runs these stages in order:

1. Git status, fetch, and fast-forward pull.
2. Environment file verification for `/etc/artsfolio/artsfolio.env`.
3. PHP syntax checks.
4. Database migrations.
5. Migration integrity checks.
6. Preflight tests with `ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env`.
7. Required service restarts for `php8.4-fpm`, `caddy`, `artsfolio-email-worker.service`, and `artsfolio-background-worker.service`.
8. Production health check.

The deploy script prints a final `DEPLOY SUCCEEDED` or `DEPLOY FAILED` banner for every normal exit path. SIGINT and SIGTERM are treated as deployment failures and exit with code 130.

The background worker is required in production. If `artsfolio-background-worker.service` is missing or cannot become active, deploy fails. The production health check also fails when the background worker is missing or inactive.

Run production deploy with:

```bash
cd /var/www/artsfolio
scripts/deploy/deploy_production.sh
```

If the script fails, inspect the command output immediately above the final failure banner, correct the failed stage, and rerun the deploy.

<!-- End of file. -->
