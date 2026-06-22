# Migration discipline

Phase 9 makes migration history tamper-evident and enforces modern numbering.

## Rules

- Existing historical duplicate prefixes remain untouched.
- Prefixes `0038` and later must be unique.
- Modern prefixes must be contiguous.
- Applied migration files must remain in the repository.
- Every migration receives a SHA-256 checksum in `schema_migrations`.
- An applied migration file must never be edited. Add a new migration instead.

## Checks

```bash
php scripts/test/migration_numbering_static.php
php scripts/database/check_migration_integrity.php
php scripts/database/check_schema_health.php
```

`migrate.php` backfills current checksums when migration 0044 first adds the checksum column. Future checksum differences fail both migration execution and integrity checks.

## Adding a migration

1. Use the next four-digit prefix.
2. Make the migration idempotent where MariaDB supports it.
3. Add schema-health expectations for critical tables/columns.
4. Add focused tests and preflight wiring.
5. Update `PROJECT_STATE.md` and `fix.txt`.
6. Never modify a migration after it has been applied outside a disposable database.
