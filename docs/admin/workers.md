# Worker operations

Open **Platform Admin → Jobs** to review background and email queue health. The health panel reports queued, active, failed, oldest queued age, and fresh worker heartbeats.

Open **Platform Admin → Workers** for per-instance heartbeat names such as `background-1` and `email-1`. A heartbeat older than 75 seconds is stale.

## Service checks

```bash
systemctl status 'artsfolio-background-worker@*.service' --no-pager
systemctl status 'artsfolio-email-worker@*.service' --no-pager
```

A stale job or email is automatically requeued after 30 minutes by default. Review `last_error` after recovery because the original process may have performed external work before it stopped.

# End of file.
