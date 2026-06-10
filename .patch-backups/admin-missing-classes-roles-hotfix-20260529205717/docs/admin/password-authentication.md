# Local Password Authentication Administration

## Supported status

Local email/password authentication is supported alongside OAuth/OIDC.

## Operational requirements

Before production use, add:

```text
rate limiting
CSRF protection
secure session cookie settings
password reset flow
email verification flow
audit logging for login events
failed login tracking
account recovery policy
```

## Storage rule

Raw session tokens and reset tokens must not be stored.

Store hashes only:

```text
SHA-256(token)
```

<!-- End of file. -->
