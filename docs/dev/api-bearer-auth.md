# API Bearer Authentication

## Scope

ArtsFolio APIs use OAuth2 bearer tokens.

## Current endpoint

```text
GET /api/me
```

## Manual verification

Create or verify a password-auth test user:

```bash
php scripts/test/password_auth.php
```

Create a development bearer token:

```bash
php scripts/test/create_dev_bearer_token.php password-auth-test@example.test
```

Use returned `access_token`:

```bash
TOKEN="paste-token-here"
php -S 127.0.0.1:8080 -t public
curl -H "Host: artsfol.io" -H "Authorization: Bearer ${TOKEN}" http://127.0.0.1:8080/api/me
```

## Storage rule

Raw bearer tokens are not stored. Only SHA-256 hashes are stored in:

```text
oauth_access_tokens.token_hash
```

## Current limitations

```text
No authorization-code flow yet
No refresh token rotation yet
No scope middleware yet
No token introspection endpoint yet
No real OAuth client secret validation yet
```

<!-- End of file. -->
