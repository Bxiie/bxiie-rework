# Operating Scale Readiness

Before launch, use the scale fixture tooling to check ArtsFolio with many tenants and media-heavy catalogs.

## Apply the update from Downloads

The update package and apply script should be in `/Users/bxiie/Downloads`.

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
chmod +x /Users/bxiie/Downloads/apply_artsfolio_phase0_phase1_scale_admin_update.sh
/Users/bxiie/Downloads/apply_artsfolio_phase0_phase1_scale_admin_update.sh /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/database/migrate.php
./scripts/test/preflight.sh
```

## Add synthetic tenants from platform admin

Open:

```text
https://artsfol.io/platform/admin/scale-tenants
```

For local development, use the matching local platform-admin URL.

The page can create, reset, and remove synthetic tenants. The default test dataset is:

```text
1000 tenants
50 artworks per tenant
200 analytics events per tenant
```

Synthetic tenants use slugs like `scale-0001` and hostnames like `scale-0001.artsfol.io`.

## Add synthetic tenants from CLI

```bash
php scripts/dev/seed_scale_dataset.php seed --tenants=1000 --artworks-per-tenant=50 --events-per-tenant=200
```

## Remove synthetic tenants

From platform admin, use:

```text
/platform/admin/scale-tenants
```

Or from CLI:

```bash
php scripts/dev/seed_scale_dataset.php cleanup
```

Cleanup is constrained to tenants that have both a `scale-` slug and the `scale_dataset_marker` tenant setting. It is designed not to affect real tenant data.

## Media variant maintenance

After adding or importing media outside the normal upload path, run:

```bash
ARTSFOLIO_MEDIA_VARIANT_BACKFILL_LIMIT=500 php scripts/maintenance/backfill_media_variants.php
```

Repeat until the output shows no more processed rows.

## Checks

```bash
php scripts/test/scale_fixture_static.php
php scripts/test/media_variants_static.php
php scripts/test/platform_scale_tenants_static.php
./scripts/test/preflight.sh
```

<!-- End of file. -->
