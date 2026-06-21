# Scale Readiness Fixtures

ArtsFolio includes an isolated scale fixture service for checking tenant, artwork, media, user, plan, and analytics behavior before launch.

## Apply the Phase 0/1 + platform admin update

The downloadable update files are expected at `/Users/bxiie/Downloads` on the workstation.

Run from the workstation repo:

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
chmod +x /Users/bxiie/Downloads/apply_artsfolio_phase0_phase1_scale_admin_update.sh
/Users/bxiie/Downloads/apply_artsfolio_phase0_phase1_scale_admin_update.sh /Users/bxiie/Dropbox/tcdev/artsfolio
```

Then apply any later hotfix archives from `/Users/bxiie/Downloads`, run the migration and checks:

```bash
php scripts/database/migrate.php
php scripts/test/scale_fixture_static.php
php scripts/test/media_variants_static.php
php scripts/test/platform_scale_tenants_static.php
./scripts/test/preflight.sh
```

## Platform admin controls

Platform owners and admins can manage synthetic tenants at:

```text
/platform/admin/scale-tenants
```

The page supports:

- queue create or update scale tenant jobs
- queue reset jobs that remove existing scale tenants, then seed
- queue remove jobs for marked scale tenants
- view current scale fixture counts, generated user counts, and plan distribution

The web action requires typed confirmation before creating or removing data. The default tenant count is `1000`; the tenant field intentionally has no artificial maximum so operators can create any positive number of scale tenants that the local/staging environment can handle.

## Fixture shape

Each synthetic tenant receives:

- slug like `scale-0001`
- hostname like `scale-0001.artsfol.io`
- marker setting `scale_dataset_marker = artsfolio-scale-fixture-v1`
- plan assignment rotating through `free`, `studio`, `pro`, and `collective`
- billing-plan setting matching the assigned plan
- at least one active tenant user
- admin/owner user count matching the plan's `allowed_admin_users` limit when available
- generated users with emails under `@scale-fixtures.artsfol.io`
- generated artwork/media/analytics data according to the requested counts

Generated users use the password `ScaleTenantFixture!2026` for local testing. Do not treat these users as real accounts.

## Background worker requirement

Platform-admin scale actions create background jobs. Make sure the worker is running before using the web controls:

```bash
systemctl status artsfolio-background-worker.service --no-pager
journalctl -u artsfolio-background-worker.service -n 100 --no-pager
```

For local development without systemd, run queued jobs manually from the project root:

```bash
php scripts/workers/run_once.php
```

Repeat `run_once.php` until the queued `scale_tenants.seed` or `scale_tenants.cleanup` job has completed. Progress and failures are visible at `/platform/admin/jobs`.

## CLI script

Run from the project root:

```bash
php scripts/dev/seed_scale_dataset.php seed --tenants=1000 --artworks-per-tenant=50 --events-per-tenant=200
```

Remove only generated scale tenants and their generated users:

```bash
php scripts/dev/seed_scale_dataset.php cleanup
```

Reset and reseed:

```bash
php scripts/dev/seed_scale_dataset.php reset --tenants=1000 --artworks-per-tenant=50 --events-per-tenant=200
```

## Isolation rules

The cleanup path only targets tenants that match both of these conditions:

- tenant slug starts with `scale-`
- tenant setting `scale_dataset_marker` equals `artsfolio-scale-fixture-v1`

Scale users are deleted only when they are linked to marked scale tenants and their email address ends with `@scale-fixtures.artsfol.io`. Cleanup also removes their tenant memberships, tenant users, identities, sessions, OAuth tokens, reset/verification tokens, role assignments, and email outbox rows before deleting the users.

This two-key tenant marker plus fixture-only user email domain is intentional. It prevents a real tenant or real user from being removed accidentally.

## Production guard

The CLI script refuses to run in production-looking environments, including `/var/www/artsfolio`, `APP_ENV=production`, `ARTSFOLIO_ENV=production`, or an env file under `/etc/artsfolio/`.

For a disposable staging database that happens to use production-like paths, pass:

```bash
php scripts/dev/seed_scale_dataset.php reset --allow-production-like --tenants=1000
```

Use that only after confirming the database is disposable.

The platform-admin web controls are intentionally guarded by platform owner/admin role checks and typed confirmation. Do not expose platform-admin access to non-operators.

## Verification

```bash
php scripts/test/scale_fixture_static.php
php scripts/test/media_variants_static.php
php scripts/test/platform_scale_tenants_static.php
./scripts/test/preflight.sh
```

<!-- End of file. -->
