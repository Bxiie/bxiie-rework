# Platform Admin Routes

## Scope

A placeholder platform admin route exists.

## Route

```text
GET /admin
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
php scripts/test/password_auth.php
php scripts/test/platform_admin_role.php
php -S 127.0.0.1:8080 -t public
```

Then log in at:

```text
http://127.0.0.1:8080/login
```

Use:

```text
password-auth-test@example.test
local-test-password
```

Then open:

```text
http://127.0.0.1:8080/admin
```

## Current limitation

This is only a protected placeholder dashboard.

<!-- End of file. -->
