# Migration Integrity

## Scope

A migration integrity checker verifies that known migration records match expected database tables.

## Command

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/database/check_migration_integrity.php
```

## Checks

The checker reports:

```text
migration_recorded_but_table_missing
table_exists_but_migration_not_recorded
```

## Why this exists

Local development hit a case where a migration created tables but failed before recording itself in `schema_migrations`.

That caused the next migration to be blocked by an already-existing table.

## Expected output

Healthy state:

```json
{
    "ok": true,
    "problem_count": 0,
    "problems": []
}
```

## Repair pattern

If a migration created all expected tables but was not recorded:

```sql
INSERT IGNORE INTO schema_migrations (migration, applied_at)
VALUES ('migration_file.sql', CURRENT_TIMESTAMP);
```

Only do this after confirming every table expected from that migration exists.

<!-- End of file. -->
