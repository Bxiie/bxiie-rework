# Lifecycle Email Queue

## What gets queued for a new tenant admin

```text
6 hours: welcome email
1 day: setup deep dive
1 week: weekly check-in
```

## What gets queued for a cancelled tenant admin

```text
6 hours: sorry to see you go
1 week: try again prompt
1 month: final win-back prompt
```

## Operational checks

Platform admins can review outgoing email state at:

```text
https://artsfol.io/admin/email-outbox
```

The worker status can be checked at:

```text
https://artsfol.io/admin/workers
```

<!-- End of file. -->
