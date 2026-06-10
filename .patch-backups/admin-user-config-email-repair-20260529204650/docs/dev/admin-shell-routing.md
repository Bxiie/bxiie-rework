# Admin shell routing contract

This project has three presentation contexts:

1. public platform/site pages
2. platform admin pages
3. tenant admin pages

## Route prefixes

| Context | Canonical prefix | Layout |
| --- | --- | --- |
| Platform admin | `/platform/admin/*` | `App\Http\View\AdminLayout` |
| Tenant admin | `/admin/*` on tenant domains | `App\Http\View\TenantAdminLayout` |
| Public/help | public routes | public/help controllers |

Platform-host `/admin/*` routes are compatibility redirects to `/platform/admin/*`. Tenant-host `/admin/*` routes are tenant administration routes and are dispatched before the platform router is built.

## Layout safety fallback

Older tenant controllers still import `App\Http\View\AdminLayout`. During the refactor, `AdminLayout::render()` checks the current host and request path. If the request is a tenant-host `/admin/*` request, it delegates to `TenantAdminLayout` instead of rendering the platform shell.

This prevents platform navigation from leaking into tenant pages while controllers are cleaned up incrementally.

## New development rule

New platform admin controllers should use:

```php
use App\Http\View\AdminLayout;
```

New tenant admin controllers should use:

```php
use App\Http\View\TenantAdminLayout;
```

Do not add platform links to `TenantAdminLayout`. Do not add tenant links to `AdminLayout`.

# End of file.
