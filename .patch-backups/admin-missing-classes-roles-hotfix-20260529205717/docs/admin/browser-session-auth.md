# Browser Session Authentication Administration

## Supported authentication models

ArtsFolio supports:

```text
OAuth/OIDC
Local email/password
```

## Current local-password browser routes

```text
GET  /login
POST /login/password
GET  /me
POST /logout
```

## Production requirements before launch

```text
TLS-only cookies with Secure flag
rate limiting
login audit logging
failed login tracking
CSRF hardening
password reset flow
email verification flow
session revocation UI
```

<!-- End of file. -->
