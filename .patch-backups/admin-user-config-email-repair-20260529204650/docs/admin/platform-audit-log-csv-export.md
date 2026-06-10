# Platform Audit Log CSV Export

## Current export

```text
GET /admin/audit-log.csv
```

## Current fields

```text
id
tenant_id
user_id
action
entity_type
entity_id
details
ip_address
created_at
```

## Future requirements

```text
date range filters
large export background jobs
export audit event
download retention policy
```

<!-- End of file. -->
