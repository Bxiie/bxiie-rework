# Tenant Admin Routes

## Scope

A placeholder tenant admin route exists.

## Route

```text
GET /admin
```

On a tenant host such as:

```text
bxiie.com
bxiie.artsfol.io
```

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
php scripts/test/password_auth.php
php scripts/test/tenant_admin_role.php
php -S 127.0.0.1:8080 -t public
```

Login at the platform route:

```text
http://127.0.0.1:8080/login
```

Then request with tenant host header:

```bash
curl -b cookies.txt -H "Host: bxiie.com" http://127.0.0.1:8080/admin
```

Browser verification is easier once local hostnames are configured.

## Current limitation

This is only a protected placeholder dashboard.

<!-- End of file. -->
