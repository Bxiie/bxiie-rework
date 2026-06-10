# Background Job Cancelled Status

## Scope

Background jobs now support a first-class `cancelled` status.

## Migration

```text
0013_background_jobs_cancelled_status.sql
```

## Status values

```text
queued
running
complete
failed
cancelled
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/database/migrate.php
php scripts/test/background_job_cancelled_status.php
php scripts/test/platform_job_actions.php
```

<!-- End of file. -->
