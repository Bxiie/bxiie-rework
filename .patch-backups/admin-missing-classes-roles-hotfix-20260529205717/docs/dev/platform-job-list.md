# Platform Background Job List

## Scope

Platform admin now has a background job list screen.

## Route

```text
GET /admin/jobs
```

## Filters

```text
status
job_type
page
limit
```

## Repository

```text
App\Platform\Jobs\JobAdminRepository
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/platform_job_list.php
```

<!-- End of file. -->
