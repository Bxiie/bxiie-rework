# API Audit Logging

## Scope

Tenant API denial paths should write audit log events when audit logging is wired into the controller.

## Current planned denial actions

```text
api.tenant_me.denied.missing_token
api.tenant_me.denied.missing_scope
api.tenant_me.denied.tenant_mismatch
api.tenant_me.denied.missing_membership_role
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/audit_log.php
```

Inspect recent audit records:

```bash
docker exec -i artsfolio-mariadb mariadb -u artsfolio -partsfolio_dev artsfolio <<'SQL'
SELECT id, tenant_id, user_id, action, entity_type, entity_id, ip_address, created_at
FROM audit_log
ORDER BY id DESC
LIMIT 20;
SQL
```

<!-- End of file. -->
