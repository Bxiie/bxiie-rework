# Public Contact and Signup Routes Administration

## Current public platform routes

```text
GET  /contact
POST /contact
GET  /
POST /signup
```

## Contact behavior

Platform `POST /contact` now behaves like tenant contact forms:

```text
validates Turnstile when configured
validates email/message
persists contact_messages with tenant_id = NULL
queues platform.contact_notification in email_outbox
```

The stored message is managed from:

```text
/platform/admin/contacts
```

## Signup behavior

Platform signup creates users and tenant setup records through the platform signup flow. Tenant public signup forms continue to create tenant email-signup records.

## Production requirements

```text
Cloudflare Turnstile keys configured
rate limiting
abuse logging
privacy policy copy
consent language for email signup
admin moderation tools
CSV export
```

<!-- End of file. -->
