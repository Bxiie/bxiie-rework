# Email outbox diagnostics

Relay errors such as `454 4.7.1 Relay access denied` mean the application reached an SMTP server, but that server refused the sender or recipient transaction. For production ArtsFolio mail, the source of truth is Platform Admin > Settings > Email delivery, not `/etc/artsfolio/artsfolio.env`.

Check these Platform Admin settings first:

- SMTP host, port, encryption, username, and password.
- From email and from name.
- Postmark message stream, when Postmark is used.
- Sender/domain verification in the SMTP provider.

For Postmark SMTP, use the Postmark server token as both SMTP username and password unless the provider account has been configured differently. Use a verified sender address or verified sending domain for the From email.

The production worker reads platform settings at send time through `EmailSenderFactory::fromPlatformSettings()`. Environment variables are only development/bootstrap fallback behavior for `EmailSenderFactory::fromEnvironment()` and should not be required for production platform-admin-managed mail.

Useful production checks:

```bash
cd /var/www/artsfolio
php scripts/workers/email_run_once.php
journalctl -u artsfolio-background-worker.service -n 100 --no-pager
grep -R "Relay access denied\|Unexpected SMTP response\|Failed email" storage logs var 2>/dev/null
```

If a queued message fails, fix Platform Admin settings and rerun the worker. Do not edit `/etc/artsfolio/artsfolio.env` unless the app cannot boot or a development fallback transport is intentionally being tested.

# End of file.
