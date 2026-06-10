# Password Reset

## Scope

Local email/password authentication now has backend password reset token support.

## Components

```text
App\Platform\Auth\Password\PasswordResetTokenRepository
App\Platform\Auth\Password\PasswordResetService
```

## Storage rule

Raw reset tokens are not stored.

Stored value:

```text
SHA-256(reset_token)
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/password_reset.php
```

Expected:

```text
reset token is created
password hash is updated
reset token is consumed
login with the new password succeeds
```

## Not implemented yet

```text
forgot-password form
reset-password form
email delivery
rate limiting
audit logging for reset request and completion
```

<!-- End of file. -->
