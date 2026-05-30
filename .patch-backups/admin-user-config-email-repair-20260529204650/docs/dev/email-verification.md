# Email Verification

## Scope

Local email/password accounts now have backend email verification token support.

## Components

```text
App\Platform\Auth\Email\EmailVerificationTokenRepository
App\Platform\Auth\Email\EmailVerificationService
```

## Storage rule

Raw verification tokens are not stored.

Stored value:

```text
SHA-256(verification_token)
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/email_verification.php
```

Expected:

```text
verification token is created
token is consumed
matching user identity receives verified_at timestamp
```

## Not implemented yet

```text
verification request route
verification browser route
email delivery
rate limiting
audit logging for verification request and completion
```

<!-- End of file. -->
