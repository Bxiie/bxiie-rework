# Platform Background Job Detail

## Scope

Platform admins can view full background job details.

## Route

```text
GET /admin/jobs/{id}
```

## Shows

```text
job metadata
full payload
full last_error
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/platform_job_detail.php
```

<!-- End of file. -->
