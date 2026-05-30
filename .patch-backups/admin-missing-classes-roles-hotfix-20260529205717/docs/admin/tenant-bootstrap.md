# Tenant Bootstrap Admin Workflow

## Create a tenant

Run the bootstrap script with tenant slug, display name, domains, and first admin email.

## Tenant login URL

Tenant admins should sign in at:

```text
https://tenant-domain/login
```

Do not send tenant admins to the domain root for login. The root is public site content.

## Post-bootstrap checks

1. Open the tenant public domain.
2. Open `/login`.
3. Sign in as the tenant admin.
4. Open `/admin`.
5. Confirm contact messages and email signups screens load.

<!-- End of file. -->
