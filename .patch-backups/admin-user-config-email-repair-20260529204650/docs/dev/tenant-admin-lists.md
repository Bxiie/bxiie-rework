# Tenant Admin Lists

## Scope

Tenant admin now has read-only list screens for public-form records.

## Routes

```text
GET /admin/contact-messages
GET /admin/email-signups
```

On tenant hosts.

## Access

Requires one of:

```text
tenant owner
tenant admin
tenant editor
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/contact_signup_records.php
php scripts/test/tenant_admin_role.php
php scripts/test/tenant_admin_lists.php
php -S 127.0.0.1:8080 -t public
```

Log in, then visit:

```text
/admin/contact-messages
/admin/email-signups
```

<!-- End of file. -->
