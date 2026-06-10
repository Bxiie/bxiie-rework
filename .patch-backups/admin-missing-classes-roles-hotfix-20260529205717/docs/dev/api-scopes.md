# API Scopes

## Scope

API bearer tokens now require route-level scope checks.

## Current implemented scope

```text
api:read
```

## Current protected endpoint

```text
GET /api/me
```

The endpoint requires:

```text
api:read
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/require_scope.php
php scripts/test/create_dev_bearer_token.php password-auth-test@example.test
```

Start local server:

```bash
php -S 127.0.0.1:8080 -t public
```

Use returned token:

```bash
TOKEN="paste-token-here"
curl -H "Host: artsfol.io" -H "Authorization: Bearer ${TOKEN}" http://127.0.0.1:8080/api/me
```

## Future scopes

Likely first scopes:

```text
api:read
api:write
tenant:read
tenant:write
artwork:read
artwork:write
media:read
media:write
billing:read
billing:write
```

<!-- End of file. -->
