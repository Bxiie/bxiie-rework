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
