# Platform Background Job Actions

## Scope

Platform admins can perform basic job maintenance actions.

## Route

```text
POST /admin/jobs/action
```

## Actions

```text
requeue
cancel
```

## Notes

Cancel only affects currently queued jobs.

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/platform_job_actions.php
```

<!-- End of file. -->
