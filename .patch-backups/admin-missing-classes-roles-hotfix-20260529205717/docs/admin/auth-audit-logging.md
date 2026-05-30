# Auth Audit Logging Administration

## Current coverage

Local password login and logout actions write audit records when the controller is wired with `AuditLogRepository`.

## Current actions

```text
auth.password_login.denied.invalid_csrf
auth.password_login.failed
auth.password_login.succeeded
auth.logout.denied.invalid_csrf
auth.logout.succeeded
```

## Production requirements

Before launch:

```text
add OAuth/OIDC login audit events
add password reset audit events
add email verification audit events
add session revocation audit events
add failed-login threshold monitoring
add platform admin audit viewer
```

<!-- End of file. -->
