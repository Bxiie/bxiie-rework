# Tenant Role API Access

## Scope

Tenant API routes now support tenant membership and role checks.

## Current tenant API check stack

```text
bearer token exists
token has api:read scope
token tenant_id is null or matches current tenant
user has tenant membership role
```

## Current allowed tenant roles for GET /api/me

```text
owner
admin
editor
viewer
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/password_auth.php
php scripts/test/tenant_role_access.php
php scripts/test/create_dev_bearer_token.php password-auth-test@example.test bxiie api:read
```

Start server:

```bash
php -S 127.0.0.1:8080 -t public
```

Use returned token:

```bash
TOKEN="paste-token-here"
curl -H "Host: bxiie.com" -H "Authorization: Bearer ${TOKEN}" http://127.0.0.1:8080/api/me
```

<!-- End of file. -->
