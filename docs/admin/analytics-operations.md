# Analytics operations

The background jobs page should show a recurring `analytics.rollup` job. Normal execution is every five minutes. Review execution duration and errors at `/platform/admin/jobs`.

Manual recovery:

```bash
cd /var/www/artsfolio
php scripts/maintenance/rebuild_analytics_rollups.php 30
php scripts/workers/run_once.php
```

Local HTTP smoke probes are marked and no longer add expected missing-token denials to the audit log. External missing-token requests remain audited.

<!-- End of file. -->
