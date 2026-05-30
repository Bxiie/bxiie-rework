# Local Email/Password Authentication

## Scope

ArtsFolio supports local email/password authentication alongside OAuth/OIDC.

## Current backend components

```text
App\Platform\Auth\Password\PasswordAuthService
App\Platform\Auth\Session\SessionRepository
App\Platform\Auth\Session\SessionTokenService
App\Platform\Identity\PasswordHasher
```

## Session storage

Browser sessions are stored in:

```text
user_sessions
```

Raw session tokens are not stored. Only SHA-256 hashes are persisted.

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/password_auth.php
```

Expected:

```text
registration or existing user lookup succeeds
password verification succeeds
session row is created
session can be looked up by hashed token
```

## Not implemented yet

```text
login form
logout route
session cookie middleware
CSRF protection
password reset controller
email verification controller
rate limiting
account lockout policy
```

<!-- End of file. -->
