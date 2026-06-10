# Tenant Admin Navigation

Tenant public navigation must link to `/admin` on the current tenant host. Do not link tenant public pages to `/platform/admin`; that route belongs to the global platform host.

Regression coverage: `scripts/test/http_smoke.sh` includes a static check that fails if tenant controllers render a platform-admin href.

# End of file.
