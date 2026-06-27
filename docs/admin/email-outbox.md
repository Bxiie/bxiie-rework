# Email Outbox Administration

## Current state

Outbound email requests are queued in `email_outbox`.

## Production requirements

Before launch:

```text
SMTP or transactional email provider configuration
email worker process
retry/backoff policy
bounce handling
unsubscribe handling
tenant email sender policy
audit logging for sensitive emails
```

## Current templates

```text
template/auth/password-reset-request.md
template/auth/email-verification-request.md
template/lifecycle/welcome.md
```

<!-- End of file. -->

## Queued-but-not-sent timestamps

Outbox timestamps are stored and monitored in UTC. If Platform Admin → Email Outbox shows queued rows and the monitor reports `queue.email.oldest_ready_age_minutes`, first check that `artsfolio-email-worker@1.service` and `artsfolio-email-worker@2.service` are active. Then run `ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php scripts/workers/email_run_once.php` from `/var/www/artsfolio` to force a single claim/send cycle and print the transport result.

# End of file.
