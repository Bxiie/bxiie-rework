# Tenant Admin Layouts

## Scope

Tenant admin list screens now use the shared admin layout.

## Updated screens

```text
/admin/contact-messages
/admin/email-signups
/admin/audit-log
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/tenant_admin_layouts.php
php -S 127.0.0.1:8080 -t public
```

<!-- End of file. -->
