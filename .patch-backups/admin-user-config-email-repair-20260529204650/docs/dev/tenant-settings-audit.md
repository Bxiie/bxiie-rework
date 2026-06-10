# Tenant Settings Audit

## Scope

Tenant settings updates can now write audit log rows.

## Audited action

```text
tenant.settings.updated
```

## Browser route

```text
POST /admin/settings
```

## Manual inspection

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/tenant_settings_audit.php
```

## Notes

Repository-level scripts do not trigger controller audit logging. Audit events are created when the HTTP controller handles the settings update.

<!-- End of file. -->
