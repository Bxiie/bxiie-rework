# Tenant Admin Action Audit

## Scope

Tenant admin state-changing actions can now write audit log rows.

## Audited actions

```text
tenant.contact_message.status_changed
tenant.email_signup.consent_changed
```

## Browser-triggered routes

```text
POST /admin/contact-messages/status
POST /admin/email-signups/consent
```

## Manual inspection

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/tenant_admin_action_audit.php
```

## Notes

The repository-level smoke tests do not trigger controller audit logging. Audit events are created when the HTTP controllers are used.

<!-- End of file. -->
