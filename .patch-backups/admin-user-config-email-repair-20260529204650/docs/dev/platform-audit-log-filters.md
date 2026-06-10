# Platform Audit Log Filters

## Scope

The platform audit log screen can now filter audit events.

## Route

```text
GET /admin/audit-log
```

## Query parameters

```text
action
tenant_id
user_id
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/audit_log_search.php
```

Browser examples:

```text
/admin/audit-log?action=tenant.settings.updated
/admin/audit-log?tenant_id=1
/admin/audit-log?user_id=2
```

<!-- End of file. -->
