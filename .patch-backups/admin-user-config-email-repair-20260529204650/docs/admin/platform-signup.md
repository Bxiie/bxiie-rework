# Platform Signup Administration

## Signup URL

```text
https://artsfol.io/signup
```

## Created records

A successful signup creates a tenant, admin user, tenant membership, role assignment, platform subdomain, provisioning jobs, and onboarding email rows.

## Post-signup checks

1. Verify tenant exists.
2. Verify `<slug>.artsfol.io` resolves after DNS/Caddy support is configured.
3. Verify `/login` loads on the tenant domain.
4. Verify lifecycle email rows in platform email outbox.
5. Verify provisioning jobs in platform jobs.

<!-- End of file. -->
