# Tenant Admin Settings

## Scope

Tenant admins now have a placeholder settings page.

## Routes

```text
GET  /admin/settings
POST /admin/settings
```

On tenant hosts only.

## Current editable settings

```text
site_title
site_admin_email
```

## Access

Requires:

```text
tenant owner
tenant admin
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/password_auth.php
php scripts/test/tenant_admin_role.php
php scripts/test/tenant_settings_admin.php
php -S 127.0.0.1:8080 -t public
```

Log in as:

```text
password-auth-test@example.test
local-test-password
```

Then browse tenant admin settings with a tenant hostname.

## Current limitation

The page is unstyled and supports only two settings.

<!-- End of file. -->
