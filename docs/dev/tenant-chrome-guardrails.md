# Tenant chrome guardrails

Tenant public pages must not call `PlatformChrome::platformAdminLink()` directly. Platform admin routes live under `/platform/admin` on `artsfol.io`; tenant public pages must link signed-in users to `/admin` on the current tenant host.

Tenant JavaScript assets must be loaded with external script tags. Do not inline `public/assets/tenant-forms.js` with `file_get_contents()` because malformed concatenation can render JavaScript as visible page text.

The static test `scripts/test/tenant_chrome_static.php` enforces these rules during preflight.

# End of file.
