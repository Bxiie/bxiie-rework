# Tenant custom public page routing

Tenant public page slugs are stored in tenant settings as `portfolio_slug`, `about_slug`, and `contact_slug`.

`app/Http/AppKernel.php` loads those values before invoking `app/Http/Routes/tenant.php`. The tenant route registrar mounts both the configured route and the historical default route. Contact mounts both GET and POST handlers under the configured slug.

The defaults remain registered for backward compatibility and to preserve old bookmarks, search-engine URLs, and Contact form targets.

Regression coverage lives in `scripts/test/custom_public_page_slugs_static.php` and is included in `scripts/test/preflight.sh`.

<!-- End of file. -->
