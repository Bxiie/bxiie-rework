# Scale Readiness Fixtures

ArtsFolio includes an isolated scale fixture service for checking tenant, artwork, media, and analytics behavior before launch.

## Apply the Phase 0/1 + platform admin update

The downloadable update files are expected at `/Users/bxiie/Downloads` on the workstation.

Run from the workstation repo:

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
chmod +x /Users/bxiie/Downloads/apply_artsfolio_phase0_phase1_scale_admin_update.sh
/Users/bxiie/Downloads/apply_artsfolio_phase0_phase1_scale_admin_update.sh /Users/bxiie/Dropbox/tcdev/artsfolio
```

Then run the migration and checks:

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

- create or update scale tenants
- reset existing scale tenants, then seed
- remove scale tenants
- view current scale fixture counts

The web action requires typed confirmation before creating or removing data.

## CLI script

Run from the project root:

```bash
php scripts/dev/seed_scale_dataset.php seed --tenants=1000 --artworks-per-tenant=50 --events-per-tenant=200
```

Remove only generated scale tenants:

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

This two-key marker is intentional. It prevents a real tenant with a similar slug from being removed accidentally.

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
