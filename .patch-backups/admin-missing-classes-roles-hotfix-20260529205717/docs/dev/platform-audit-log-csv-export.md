# Platform Audit Log CSV Export

## Scope

The platform audit log can now be exported as CSV.

## Route

```text
GET /admin/audit-log.csv
```

## Supported filters

```text
action
tenant_id
user_id
```

## Examples

```text
/admin/audit-log.csv
/admin/audit-log.csv?action=tenant.settings.updated
/admin/audit-log.csv?tenant_id=1
/admin/audit-log.csv?user_id=2
```

## Access

Requires one of:

```text
platform owner
platform admin
platform support
```

<!-- End of file. -->
