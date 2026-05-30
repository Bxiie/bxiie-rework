# Tenant Bootstrap

## Purpose

`scripts/tenant/bootstrap_tenant.php` creates or updates a tenant, tenant domain rows, the first tenant admin user/membership, starter portfolio sections when the table exists, and a delayed welcome email when the outbox schema supports it.

## Command

```bash
ARTSFOLIO_ENV_FILE=.env.local php scripts/tenant/bootstrap_tenant.php \
  --slug=bxiie \
  --name="Bxiie" \
  --domain=bxiie.com \
  --domain=www.bxiie.com \
  --domain=bxiie.artsfol.io \
  --admin-email=password-auth-test@example.test \
  --admin-name="Bxiie Admin"
```

## Login routing

Tenant login must be hosted at:

```text
https://tenant-domain/login
```

The tenant domain root remains the public tenant site.

## Notes

The bootstrap script uses schema introspection so it can run across local and production schema drift while the refactor is still settling.

<!-- End of file. -->
