# Tenant Audit Log Screen

## Scope

Tenant admins now have a tenant-filtered audit log screen.

## Routes

```text
GET /admin/audit-log
GET /admin/audit-log.csv
```

On tenant hosts only.

## Filters

```text
action
user_id
```

Tenant ID is forced to the current tenant and cannot be supplied by the browser.

## Access

Requires one of:

```text
tenant owner
tenant admin
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/tenant_admin_role.php
php scripts/test/tenant_audit_log_list.php
```

<!-- End of file. -->
