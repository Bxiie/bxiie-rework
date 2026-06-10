# Email delivery diagnostics

Production email sending is configured from `platform_settings`, maintained in Platform Admin > Settings > Email delivery. The worker path uses `EmailSenderFactory::fromPlatformSettings()` so SMTP host, port, username, password, encryption, sender, and Postmark message stream are all database-backed.

`EmailSenderFactory::fromEnvironment()` remains available for development and bootstrap tests. It honors `EMAIL_DRIVER` and the legacy `MAIL_TRANSPORT` name. `MAIL_TRANSPORT=log` maps to dry-run behavior so old deployments do not accidentally send live mail.

`SmtpEmailSender` supports:

- plain SMTP for local test relays;
- STARTTLS with `smtp_encryption=tls`;
- implicit TLS with `smtp_encryption=ssl`;
- SMTP AUTH PLAIN when username and password are both present;
- custom safe headers such as `X-PM-Message-Stream`.

A `454 4.7.1 Relay access denied` from Postmark or another relay usually means authentication was not sent, credentials were wrong, or the configured From email/domain is not verified with that relay. Since production settings are database-backed, correct Platform Admin settings before touching environment files.

Regression checks:

```bash
php -l app/Platform/Email/EmailSenderFactory.php
php -l app/Platform/Email/SmtpEmailSender.php
php scripts/test/smtp_custom_headers.php
php scripts/test/platform_smtp_message_stream_setting.php
```

# End of file.
