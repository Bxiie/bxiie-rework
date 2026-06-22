# Artist directory operations

The public directory uses the `tenant_directory_profiles` projection table.

After migration or if cards appear stale, rebuild the projection:

```bash
cd /var/www/artsfolio
php scripts/maintenance/rebuild_directory_profiles.php
```

Expected output:

```json
{
  "tenants_synced": 1000
}
```

Verify counts:

```sql
SELECT COUNT(*) FROM tenant_directory_profiles;
SELECT COUNT(*) FROM tenant_directory_profiles WHERE is_listed = 1;
```

Directory pages display 24 artists at a time with Previous and Next controls.
