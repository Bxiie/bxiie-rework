# Auth Audit Logging

## Scope

Local password browser authentication now supports audit log events.

## Current audit actions

```text
auth.password_login.denied.invalid_csrf
auth.password_login.failed
auth.password_login.succeeded
auth.logout.denied.invalid_csrf
auth.logout.succeeded
```

## Manual verification

Start the local server:

```bash
php -S 127.0.0.1:8080 -t public
```

Login through:

```text
http://127.0.0.1:8080/login
```

Inspect auth audit events:

```bash
php scripts/test/auth_audit_log.php
```

<!-- End of file. -->
