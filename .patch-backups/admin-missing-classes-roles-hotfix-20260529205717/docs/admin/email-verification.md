# Email Verification Administration

## Current state

Email verification backend token creation and consumption exist for local email/password identities.

## Not production-ready until

```text
email delivery exists
browser verification route exists
rate limiting exists
audit logging exists
support resend workflow exists
verification expiration policy is finalized
```

## Token rule

Never store raw email verification tokens.

Only store:

```text
SHA-256(token)
```

<!-- End of file. -->
