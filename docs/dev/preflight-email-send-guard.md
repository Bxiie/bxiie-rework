# Preflight email send guard

Production deploy preflight must not send real SMTP messages to `.test` recipients. The deploy gate checks PHP syntax, migration integrity, tenant routing, email queueing, and email template rendering. It does not run the email worker send path by default.

To test the worker send path safely, configure SMTP to a local sink such as MailHog and run:

```bash
ARTSFOLIO_PREFLIGHT_SEND_EMAIL=1 ./scripts/test/preflight.sh
```

Do not enable `ARTSFOLIO_PREFLIGHT_SEND_EMAIL=1` on production unless SMTP is temporarily pointed at a non-delivering sink.

# End of file.
