# Platform Audit Log Screen

## Scope

Platform admin now has a read-only audit log list screen.

## Route

```text
GET /admin/audit-log
```

## Access

Requires one of:

```text
platform owner
platform admin
platform support
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/platform_admin_role.php
php scripts/test/platform_audit_log_list.php
php -S 127.0.0.1:8080 -t public
```

Log in, then visit:

```text
/admin/audit-log
```

<!-- End of file. -->
