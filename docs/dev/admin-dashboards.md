# Admin dashboards

The platform and tenant admin dashboards are operational command surfaces rather than static link menus.

## Platform dashboard

`app/Http/Controllers/Platform/Admin/DashboardController.php` summarizes:

- active, paid-capable, and complimentary tenants
- 30-day gross marketplace volume
- 30-day platform commission
- seller net revenue
- open contact messages
- queued, running, and failed background jobs
- recent sales
- recent tenants
- active plan economics

Queries are guarded with table/column checks so the dashboard survives rolling migrations and partially applied local environments.

## Tenant dashboard

`app/Http/Controllers/Tenant/Admin/DashboardController.php` summarizes:

- published and draft artworks
- for-sale inventory and low-stock multiple items
- 30-day analytics events and top path
- email subscribers
- open contact messages
- open sales orders
- current billing plan and sales readiness
- actionable warnings

The dashboard is tenant-scoped by `TenantContext::tenantId`; no tenant-wide dashboard query should omit the tenant predicate.

## Styling

Dashboard metric cards and split panels are styled in `public/assets/tenant-admin.css` because the platform admin shell also loads that stylesheet. Keep dashboard CSS class names shared unless a page requires a platform-only visual treatment.

# End of file.
