# Production Preflight

Production preflight may check syntax, migration integrity, service health, and non-mutating HTTP smoke behavior.

It must not create or update:

```text
tenant_settings
email_signups
contact_messages
audit_log
tenant memberships
```

Mutating test scripts must skip when the production env file is active.
