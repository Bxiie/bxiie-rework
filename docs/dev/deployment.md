# ArtsFolio Production Deployment

Production deploys are run from `/var/www/artsfolio` with `scripts/deploy/deploy_production.sh`.

The deploy script runs these stages in order:

1. Git status, fetch, and fast-forward pull.
2. Environment file verification for `/etc/artsfolio/artsfolio.env`.
3. PHP syntax checks.
4. Database migrations.
5. Migration integrity checks.
6. Preflight tests with `ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env`.
7. Service restarts for PHP-FPM, Caddy, the email worker, and the background worker when installed.
8. Production health check.

The script uses an `EXIT` trap to print a final `DEPLOY SUCCEEDED` or `DEPLOY FAILED` banner for every exit path. Failure output includes the stage name, exit code, branch, and commit so failed preflight or health-check runs are visibly different from successful deploys.

Run production deploy with:

```bash
cd /var/www/artsfolio
scripts/deploy/deploy_production.sh
```

If the script fails, inspect the command output immediately above the final failure banner, correct the failed stage, and rerun the deploy.

<!-- End of file. -->
