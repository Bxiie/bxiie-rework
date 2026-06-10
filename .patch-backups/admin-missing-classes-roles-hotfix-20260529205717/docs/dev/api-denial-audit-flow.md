# API Denial Audit Flow

## Scope

Tenant API denied-access paths now write audit log records when `TenantMeController` is constructed with `AuditLogRepository`.

## Audited denial actions

```text
api.tenant_me.denied.missing_token
api.tenant_me.denied.missing_scope
api.tenant_me.denied.tenant_mismatch
api.tenant_me.denied.missing_membership_role
```

## Manual verification

Start the dev server:

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php -S 127.0.0.1:8080 -t public
```

Trigger missing-token denial:

```bash
curl -H "Host: bxiie.com" http://127.0.0.1:8080/api/me
```

Inspect audit denials:

```bash
php scripts/test/api_denial_audit.php
```

<!-- End of file. -->
