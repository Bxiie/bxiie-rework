# Email Senders Administration

## Current sender options

```text
dry_run
smtp
```

## Recommended production direction

Use a transactional email provider adapter before launch.

The basic SMTP sender is acceptable for Mailhog/local development, not final production infrastructure.

## Required production controls

```text
provider credentials in environment/secrets manager
sender domain verification
SPF/DKIM/DMARC setup
bounce handling
unsubscribe handling
rate limits
retry policy
dead-letter handling
```

<!-- End of file. -->
