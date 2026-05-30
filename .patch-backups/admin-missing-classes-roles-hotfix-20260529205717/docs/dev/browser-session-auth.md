# Browser Session Authentication

## Scope

The HTTP layer now has local email/password browser session support.

## Routes

```text
GET  /login
POST /login/password
GET  /me
POST /logout
```

## Cookie

```text
artsfolio_session
```

Session cookie properties currently used:

```text
HttpOnly
SameSite=Lax
Path=/
Max-Age=1209600
```

Production still needs the `Secure` flag once TLS is active.

## CSRF

Password login and logout use a simple PHP-session-backed CSRF token.

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/password_auth.php
php -S 127.0.0.1:8080 -t public
```

Open:

```text
http://127.0.0.1:8080/login
```

Use:

```text
password-auth-test@example.test
local-test-password
```

## Not implemented yet

```text
OAuth/OIDC login routes
password reset browser flow
email verification browser flow
rate limiting
Secure cookie flag environment handling
remember-me controls
```

<!-- End of file. -->
