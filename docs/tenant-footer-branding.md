# Public footer branding

Settings → Identity → Show ArtsFolio branding controls the shared attribution on
public portfolio, artwork, About, Contact, cart and checkout pages (including their
error pages). Tenant-admin footers are not changed. The link opens https://artsfol.io/
in a new window with noopener/noreferrer.

The existing tenant_settings table stores show_artsfolio_branding as 1 or 0.
No schema migration or backfill is needed: absent, null and invalid values resolve
to ON for both existing and newly provisioned tenants. Only explicit 0 can opt out.

Removal is permitted for tenants with the platform-controlled complementary flag,
or a current trial/active/manual assignment to a positive-monthly-price plan other
than fee/free/starter. Complementary tenants qualify even on free plans. Pending
plan selections and the legacy editable billing_plan setting grant no entitlement.
Unknown/inactive assignments and unavailable billing data do not grant removal.

TenantSettingsRepository normalizes unauthorized OFF writes to ON and rechecks
eligibility when reading the effective setting. A downgrade or complementary flag
revocation therefore restores attribution even if a previously saved OFF remains.
Raw snapshots/all() expose stored preferences, not effective entitlement; public
renderers must use TenantBranding (which uses the enforced repository getter).

Run php scripts/test/tenant_footer_branding.php for isolated persistence, admin POST,
CSRF/access control, complementary, downgrade, tenant isolation and HTML checks.
The test uses SQLite and translates only MariaDB's settings upsert syntax.
