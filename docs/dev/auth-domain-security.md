# Auth and Domain Security

Tenant logout revokes the browser session cookie on the server by hashing the raw cookie token through `SessionTokenService` and revoking through `SessionRepository`.

Tenant password reset token issue and redeem paths are tenant-scoped. The tenant reset email is queued only when the address belongs to an active member of that tenant, and reset submission rechecks tenant membership before password mutation.

Custom domain usage ignores the default `*.artsfol.io` subdomain and collapses `www.example.com` with `example.com` for plan counting. Tenant deletion removes `tenant_domains` rows so slugs and domains can be reused.

# End of file.
