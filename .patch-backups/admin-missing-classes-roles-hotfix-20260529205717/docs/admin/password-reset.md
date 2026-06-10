# Password Reset Administration

## Current state

Password reset backend token creation and consumption exist.

## Not production-ready until

```text
email delivery exists
rate limiting exists
audit logging exists
reset request form exists
reset completion form exists
token lifetime policy is finalized
support workflow is documented
```

## Token rule

Never store raw password reset tokens.

Only store:

```text
SHA-256(token)
```

<!-- End of file. -->
