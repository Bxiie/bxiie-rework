# Bxiie Project State

## Current deployment workflow

All Bxiie CMS changes are applied first to the workstation repository:

```text
/Users/bxiie/Dropbox/artsy/site/bxiie_rework
```

Changes are committed and pushed to GitHub with `GH_TOKEN`, then production pulls through `/usr/local/bin/bxiie-git-pull`.

## Current production paths

```text
/var/www/bxiie-cms
/var/lib/bxiie-cms/database/bxiie.sqlite
/var/lib/bxiie-cms/storage
/etc/bxiie-cms
```

## Current feature state

- Multitenant PHP/SQLite CMS.
- Admin site settings include title, browser title, artist name, copyright name, tab labels, public slugs, colors, page images, background image controls, reCAPTCHA keys, and tenant CSS.
- Public content intentionally renders trusted admin-entered HTML.
- Contact form and email signup support Google reCAPTCHA when keys are configured.
- Email subscribers are viewable at `/admin/subscribers` and exportable as CSV.
- Usage stats store image views, thumbnails, search by image/location, and location aggregation.
- Location data is derived from request IP lookup after checking proxy geolocation headers, then cached in `ip_geolocations`.

## Open risks

- reCAPTCHA secret key lives in tenant settings. A future hardening pass should allow secrets to live in `/etc/bxiie-cms` instead.
- IP geolocation currently uses an external HTTP lookup when no proxy headers exist. A future hardening pass should move this to a server-side GeoIP database or trusted reverse-proxy headers.
- Admin-entered HTML is intentionally trusted. Do not give editor access to untrusted users without sanitization.

# End of file.


## Exhibitions/messages cleanup update

- Removed the global newsletter modal/subscription block from public layout pages.
- Contact-page email signup remains available.
- Added editable `exhibitions_heading` and `exhibitions_display_mode` settings under `/admin/site`.
- Public About page can display exhibitions as stacked text entries or a table.
- Added `/admin/messages/delete` for tenant-scoped contact-message deletion.
- Migration script: `scripts/migrate_exhibitions_messages_cleanup.php`.

# End of file.
