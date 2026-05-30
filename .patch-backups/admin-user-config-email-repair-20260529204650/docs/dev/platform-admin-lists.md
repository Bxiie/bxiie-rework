# Platform Admin Lists

## Scope

Platform admin now has read-only list screens.

## Routes

```text
GET /admin/tenants
GET /admin/email-outbox
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
php scripts/test/platform_admin_lists.php
php -S 127.0.0.1:8080 -t public
```

Log in, then visit:

```text
/admin
/admin/tenants
/admin/email-outbox
```

<!-- End of file. -->
