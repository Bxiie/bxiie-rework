# Worker Entrypoint Heartbeats

## Scope

Worker entrypoints can now record a heartbeat through a shared helper.

## Helper

```text
scripts/workers/heartbeat.php
```

## Function

```php
artsfolio_worker_heartbeat($workerName, $status, $details)
```

## Patched entrypoints when present

```text
scripts/workers/run_once.php
scripts/workers/email_run_once.php
```

The patch is conditional. If an entrypoint does not exist locally, it is skipped.

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/database/migrate.php
php scripts/test/worker_entrypoint_heartbeat.php
```

<!-- End of file. -->
