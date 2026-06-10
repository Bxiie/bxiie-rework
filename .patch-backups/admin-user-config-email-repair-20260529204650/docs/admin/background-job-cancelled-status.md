# Background Job Cancelled Status

## Current behavior

Platform admins can mark queued jobs as cancelled.

## Operational meaning

```text
failed = worker attempted and failed
cancelled = operator intentionally stopped a queued job
```

## Future requirements

```text
cancel only queued jobs with visible no-op message
track cancelled_by user id
track cancelled_at
show cancellation reason
```

<!-- End of file. -->
