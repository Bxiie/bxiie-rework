# Admin Dashboard Layout

## Scope

Platform and tenant admin dashboards now use the shared admin layout.

## Updated controllers

```text
app/Http/Controllers/Platform/Admin/DashboardController.php
app/Http/Controllers/Tenant/Admin/DashboardController.php
```

## Manual verification

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
php scripts/test/admin_dashboard_layouts.php
php -S 127.0.0.1:8080 -t public
```

Visit after login:

```text
Platform host: /admin
Tenant host: /admin
```

<!-- End of file. -->
