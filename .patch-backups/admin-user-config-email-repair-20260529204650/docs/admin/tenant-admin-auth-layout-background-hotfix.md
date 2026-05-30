# Tenant admin auth, layout, and background image hotfix

This change fixes tenant-admin route drift and uses one shared tenant-admin navigation source.

## Tenant background image

Tenant admins select the public background image from **Tenant Admin → Settings → Colors and background → Background image**. The list is populated from non-private tenant image media. The public site reads `tenant_settings.background_media_uuid` and renders it through `/media?uuid=...`.

## Admin access behavior

Tenant admin routes under `/admin` on a tenant host require a logged-in tenant owner/admin. Anonymous visitors are redirected to `/login?notice=admin-login-required`.

Platform admin routes under `/platform/admin` on the platform host require a platform owner/admin/support user. Anonymous visitors are redirected to `/login?notice=platform-admin-login-required`.

## Layout/menu behavior

The tenant-admin menu lives in `app/Http/View/TenantAdminNav.php`. Both the real tenant admin layout and the backward-compatible tenant admin layout shim use this source, so old controllers cannot reintroduce the stale `Discovery` tab or the wrong public-site header.

## Platform analytics

Platform-host GET requests are recorded into `analytics_events` with `tenant_id = NULL`. The migration `0017_platform_analytics_nullable_tenant.sql` makes that nullable and keeps the tenant foreign key for tenant events.

// End of file.
