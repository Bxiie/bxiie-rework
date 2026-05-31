# Email outbox diagnostics

Platform Admin → Email Outbox shows the latest outbox rows and displays the stored `email_outbox.last_error` value for failed messages. Use this field first when diagnosing SMTP, relay, sender-domain, or message-stream failures.

A relay error such as `454 4.7.1 Relay access denied` means the configured mail transport reached an SMTP server, but that server refused to accept the recipient or sender combination. Check platform SMTP settings, sender domain authorization, Postmark stream/header settings, and the worker environment.

# End of file.
