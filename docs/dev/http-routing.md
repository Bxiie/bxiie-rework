# HTTP routing architecture

`public/index.php` is intentionally a small front controller. It performs the earliest canonical-host redirect, loads the application bootstrap, installs fatal-error handling, starts the session, and delegates to `App\Http\AppKernel`.

`AppKernel` resolves tenant and user context, handles request-wide guards and special endpoints, prepares request-scoped services, and dispatches one of two route registrars:

- `app/Http/Routes/tenant.php`
- `app/Http/Routes/platform.php`

Route behavior is protected by `scripts/test/route_inventory.php` and the committed snapshot at `scripts/test/fixtures/route_inventory.json`. Update the snapshot only when intentionally adding, removing, or changing a route.

Tenant password-reset recipient validation lives in `App\Http\Auth\TenantPasswordResetGuard`, rather than as a global function in the front controller.
