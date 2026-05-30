# Platform Job Action Attempt Records

## Scope

Platform admin job actions now write attempt-history rows.

## Actions recorded

```text
admin_requeued
admin_cancelled
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/platform_job_action_attempts.php
```

## Related screen

```text
GET /admin/jobs/{id}
```

<!-- End of file. -->
