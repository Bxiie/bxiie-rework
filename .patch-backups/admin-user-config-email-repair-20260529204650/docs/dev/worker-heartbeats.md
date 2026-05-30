# Worker Heartbeats

## Scope

Background workers can now report heartbeat status.

## Migration

```text
0015_worker_heartbeats.sql
```

## Repository

```text
App\Platform\Workers\WorkerHeartbeatRepository
```

## Admin route

```text
GET /admin/workers
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/database/migrate.php
php scripts/test/worker_heartbeats.php
```

<!-- End of file. -->
