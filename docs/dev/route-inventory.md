# Route Inventory

## Purpose

`public/index.php` currently mounts tenant routes and platform routes in separate blocks.

The route inventory test prevents accidental exposure of platform-only routes on tenant domains and preserves the tenant login convention:

```text
https://tenant-domain/login
```

The tenant domain root remains public portfolio content.

## Test command

```bash
php scripts/test/route_inventory.php
```

## Important route split

Tenant routes:

```text
GET  /
GET  /login
POST /login
GET  /admin
GET  /contact
POST /signup
```

Platform routes:

```text
GET /admin
GET /admin/jobs
GET /admin/workers
GET /admin/platform-settings
```

## Notes

`GET /signup` is intentionally not a tenant route at this stage. The signup form is embedded on the tenant home page and posts to `/signup`.

<!-- End of file. -->
