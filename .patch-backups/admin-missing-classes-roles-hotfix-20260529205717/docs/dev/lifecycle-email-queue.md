# Lifecycle Email Queue

## Purpose

`scripts/email/queue_lifecycle_emails.php` queues scheduled lifecycle email rows directly into `email_outbox`.

This uses the current production path:

```text
email_outbox -> scripts/workers/email_run_once.php -> artsfolio-email-worker.service
```

## Supported lifecycles

```text
tenant_admin_onboarding
tenant_admin_cancelled
```

## Onboarding schedule

```text
tenant_admin_welcome_6h             available after 6 hours
tenant_admin_feature_deep_dive_1d   available after 1 day
tenant_admin_weekly_checkin         available after 1 week
```

## Cancellation schedule

```text
tenant_admin_cancelled_6h           available after 6 hours
tenant_admin_winback_1w             available after 1 week
tenant_admin_winback_1m             available after 1 month
```

## Command

```bash
ARTSFOLIO_ENV_FILE=.env.local php scripts/email/queue_lifecycle_emails.php \
  --tenant-slug=bxiie \
  --email=password-auth-test@example.test \
  --name="Bxiie Admin" \
  --lifecycle=tenant_admin_onboarding
```

## Idempotency

The script skips queued, sending, or sent rows with the same tenant, recipient email, and template key.

<!-- End of file. -->
