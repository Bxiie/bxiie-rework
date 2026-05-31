# Email delivery diagnostics

Email workers use `EmailSenderFactory`. Environment-backed senders now honor both `EMAIL_DRIVER` and the legacy `MAIL_TRANSPORT` name. `MAIL_TRANSPORT=log` is treated as the dry-run sender so production-like deployments do not accidentally attempt SMTP because of a variable-name mismatch.

The platform email outbox admin page must expose `email_outbox.last_error` for failed rows. Do not hide the raw transport error behind a status-only table; the transport response is the first useful diagnostic signal.

# End of file.
