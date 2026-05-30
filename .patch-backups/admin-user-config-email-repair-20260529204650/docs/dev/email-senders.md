# Email Senders

## Scope

Email delivery now uses a sender interface and factory.

## Senders

```text
DryRunEmailSender
SmtpEmailSender
```

## Environment variables

```text
EMAIL_DRIVER=dry_run
EMAIL_DRIVER=smtp

SMTP_HOST=127.0.0.1
SMTP_PORT=1025
MAIL_FROM_EMAIL=no-reply@artsfol.io
MAIL_FROM_NAME=ArtsFolio
```

## Dry-run verification

```bash
EMAIL_DRIVER=dry_run php scripts/test/email_sender_factory.php
php scripts/test/email_outbox.php
EMAIL_DRIVER=dry_run php scripts/workers/email_run_once.php
```

## Mailhog-style SMTP verification

Start Mailhog separately or use an existing local Mailhog container on port 1025.

```bash
EMAIL_DRIVER=smtp SMTP_HOST=127.0.0.1 SMTP_PORT=1025 php scripts/test/email_sender_factory.php
php scripts/test/email_outbox.php
EMAIL_DRIVER=smtp SMTP_HOST=127.0.0.1 SMTP_PORT=1025 php scripts/workers/email_run_once.php
```

## Production warning

The current SMTP sender is basic and intended for local development/Mailhog-style testing.

Production should use a hardened transactional email provider adapter.

<!-- End of file. -->
