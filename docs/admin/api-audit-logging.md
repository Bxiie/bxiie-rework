# API Audit Logging Administration

## Current model

Tenant API denied-access events should be written to `audit_log`.

## Production requirements

Before launch:

```text
audit all admin mutations
audit login success/failure
audit token creation and revocation
audit tenant membership changes
add audit log viewer in platform admin
add retention policy
```

<!-- End of file. -->
