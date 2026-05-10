# Bxiie Artist CMS Scaffold

This is a first implementation pass for the Bxiie website rebuild. It is intentionally small: PHP, SQLite, Apache rewrite rules, generated image derivatives, tenant-aware routing, admin pages, contact messages, newsletter capture, and usage statistics.

## Why this stack

The current site is static HTML and the server is already hosting bxiie.com. PHP plus SQLite keeps deployment simple while still providing a clean path to MySQL/PostgreSQL later if the multitenant product grows.

## Included

- Multitenant tenant/domain model
- `bxiie.com` as the default tenant
- `/artist/{slug}` tenant routing
- `/admin` admin section
- owner/editor/viewer roles in schema
- site colors, title, and tab label management
- image upload with original preservation and cached derivatives
- optional watermarking on generated derivatives only
- portfolio sections
- exhibition/event management
- about/contact/social settings
- contact form message capture
- newsletter subscriber capture
- usage statistics by date range, path, event type, image views, referrer/user agent/IP hash/country header
- public Home, Portfolio, About, Contact, and artwork detail pages

## Install

1. Copy this directory to the server outside the web root if possible.
2. Point Apache document root to `public/`.
3. Ensure PHP has `pdo_sqlite` and `gd` enabled.
4. Ensure `storage/` and `database/` are writable by the web server.
5. Run:

```bash
ADMIN_EMAIL='your@email.com' ADMIN_PASSWORD='replace-this' php scripts/install.php
```

6. Visit `/admin/login`.

## Immediate hardening before production

- Fix HTTPS for bxiie.com. The current public HTTPS endpoint was returning a 502 during review.
- Replace default admin password immediately.
- Add CSRF tokens to all admin forms.
- Add server-level upload limits and file size checks.
- Add rate limiting to contact and subscribe forms.
- Add outbound email delivery for contact notifications.
- Add proper role enforcement in controllers. The schema is ready, but this scaffold currently only requires login.
- Add an import script to migrate current static HTML image records into the database.

## Next implementation pass

The next pass should add edit/delete screens, image-to-section assignment, tag-based portfolio views, favicon generation, SEO/social metadata UI, CSV export of subscribers, richer analytics, and a migration/importer for the existing site content.
