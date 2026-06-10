# Platform Admin Layouts

## Scope

Platform admin list screens now use the shared admin layout.

## Updated screens

```text
/admin/tenants
/admin/email-outbox
/admin/audit-log
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/platform_admin_layouts.php
php -S 127.0.0.1:8080 -t public
```

<!-- End of file. -->
