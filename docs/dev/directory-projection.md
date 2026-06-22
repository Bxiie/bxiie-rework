# Directory projection

The public artist directory reads `tenant_directory_profiles`, not repeated joins over `tenant_settings`, `tenant_domains`, `artworks`, and `media_assets`.

## Synchronization

`TenantDirectoryProfileRepository::syncTenant()` refreshes one tenant after relevant changes:

- directory opt-in, summary, or thumbnail setting
- domain creation, removal, status, or primary-domain change
- tenant status change
- artwork status/archive change affecting the selected thumbnail

The migration backfills existing tenants. To repair drift manually:

```bash
php scripts/maintenance/rebuild_directory_profiles.php
```

The command is idempotent.

## Public reads

`/directory` reads only listed projection rows, ordered by normalized artist name, 24 rows per page.
