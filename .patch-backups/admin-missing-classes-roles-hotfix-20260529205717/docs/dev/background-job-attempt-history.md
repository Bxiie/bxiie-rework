# Background Job Attempt History

## Scope

Background jobs now have attempt-history scaffolding.

## Migration

```text
0014_background_job_attempts.sql
```

## Repository

```text
App\Platform\Jobs\JobAttemptRepository
```

## Admin UI

The job detail page shows attempt history.

```text
GET /admin/jobs/{id}
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/database/migrate.php
php scripts/test/background_job_attempts.php
```

<!-- End of file. -->
