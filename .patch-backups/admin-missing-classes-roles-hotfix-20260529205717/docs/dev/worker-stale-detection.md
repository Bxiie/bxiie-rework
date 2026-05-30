# Worker Stale Detection

## Scope

The platform Workers screen now shows an effective status.

Workers last seen more than 300 seconds ago are displayed as:

```text
stale
```

## Admin route

```text
GET /admin/workers
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/worker_stale_detection.php
```

Then open:

```text
/admin/workers
```

<!-- End of file. -->
