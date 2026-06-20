# Branded error pages

ArtsFolio must not serve browser-facing naked framework, PHP, router, or web-server error content. Error responses are rendered through `App\Http\View\ErrorPage` so users see either platform branding or tenant branding.

## Runtime flow

- `public/index.php` records request context in `$GLOBALS['artsfolio_tenant_context']` and `$GLOBALS['artsfolio_platform_context']` after tenant resolution.
- `App\Http\Router::dispatch()` returns `Response::notFound()` with visitor-safe copy when no route matches.
- `App\Http\Response::error()` renders known status codes through `ErrorPage::status()`.
- `public/index.php` catches uncaught `Throwable` instances and calls `ErrorPage::sendException()`.
- `public/index.php` registers a shutdown handler for fatal PHP errors and calls `ErrorPage::sendFatal()` when possible.
- `public/.htaccess` maps common Apache `ErrorDocument` statuses to `/error/{code}`, which routes back through the application renderer.

## Branding rules

Tenant hosts render tenant-branded error pages using the resolved tenant name and a `Powered by ArtsFolio` footer. Platform hosts and `/platform` routes render ArtsFolio platform branding.

The renderer intentionally avoids database reads and tenant settings lookups. Error paths need to work when persistence, settings, or downstream services are broken.

## Regression coverage

Run:

```bash
php scripts/test/branded_error_pages_static.php
./scripts/test/preflight.sh
```

The static test verifies that raw markers such as `No route for`, `Application error`, and `Stack trace` are not present in the front-controller/router error path.

## Operational checks

After deployment, verify both platform and tenant missing pages:

```bash
curl -ksS https://artsfol.io/__definitely_missing_platform_page__ | grep -E 'ArtsFolio|error-card'
curl -ksS https://bxiie.artsfol.io/__definitely_missing_tenant_page__ | grep -E 'James Payne Art|Powered by ArtsFolio|error-card'
```

Also confirm raw leakage is absent:

```bash
curl -ksS https://artsfol.io/__definitely_missing_platform_page__ | grep -E 'No route for|Application error|Fatal error|Stack trace|Apache/[0-9]' && exit 1 || true
curl -ksS https://bxiie.artsfol.io/__definitely_missing_tenant_page__ | grep -E 'No route for|Application error|Fatal error|Stack trace|Apache/[0-9]' && exit 1 || true
```


## CSRF failures

Invalid or expired CSRF token responses must use `Response::invalidCsrf()` so security failures render inside the same platform or tenant branded error shell as 404 and 500 responses. Controllers must not return raw `<h1>Invalid CSRF token</h1>` markup.


## Platform user lifecycle validation

`Platform\Admin\UsersController` must use `Response::error()` for invalid user lifecycle requests. The regression check in `scripts/test/platform_user_lifecycle_static.php` verifies that platform user lifecycle failures are branded and that the list reads real `users.status` values rather than hardcoded active status.

<!-- End of file. -->


