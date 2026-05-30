# Email Worker Administration

## Current state

The email worker is dry-run only.

## Local command

```bash
php scripts/workers/email_run_once.php
```

## Production requirements

Before production launch:

```text
systemd service or queue worker process
SMTP or transactional email provider
retry and dead-letter policy
bounce handling
unsubscribe handling
delivery audit trail
tenant-level sender policy
```

<!-- End of file. -->
