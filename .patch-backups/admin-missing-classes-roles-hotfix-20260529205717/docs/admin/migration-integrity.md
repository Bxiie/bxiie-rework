# Migration Integrity Administration

## Current tool

```bash
php scripts/database/check_migration_integrity.php
```

## Operational use

Run before deployment and after migration failures.

## Important warning

Do not manually mark a migration as applied unless the expected schema objects already exist and have been reviewed.

<!-- End of file. -->
