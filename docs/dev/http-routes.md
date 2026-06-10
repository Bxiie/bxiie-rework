# ArtsFolio HTTP and API route reference

Developer reference is available in the application at `/help/developer` after login. External integrations must use OAuth 2.0 bearer tokens and send `Authorization: Bearer <token>`.

## Authentication

### GET /login
Render the local login form. Browser-only.

```bash
curl -i https://artsfol.io/login
```

### POST /login
Submit email/password login. The response sets `artsfolio_session` for `artsfol.io` and `*.artsfol.io` when served from those hosts.

```bash
curl -i -X POST https://artsfol.io/login \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'email=admin@example.com' \
  -d 'password=correct-horse-battery-staple'
```

## Platform API

### GET /api/admin/tenants
List tenants. Requires OAuth scope `platform:write` or `*`.

```bash
curl -s https://artsfol.io/api/admin/tenants \
  -H 'Authorization: Bearer ACCESS_TOKEN'
```

### POST /api/admin/tenants
Create a tenant and initial admin. Requires `platform:write` or `*`.

```bash
curl -s -X POST https://artsfol.io/api/admin/tenants \
  -H 'Authorization: Bearer ACCESS_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{"slug":"demo","name":"Demo Artist","admin_email":"admin@example.com","admin_name":"Demo Admin","password":"change-this-long-password"}'
```

### GET /api/admin/tenants/{id}
Fetch one tenant.

```bash
curl -s https://artsfol.io/api/admin/tenants/1 \
  -H 'Authorization: Bearer ACCESS_TOKEN'
```

### POST /api/admin/tenants/{id}
Update tenant name or status.

```bash
curl -s -X POST https://artsfol.io/api/admin/tenants/1 \
  -H 'Authorization: Bearer ACCESS_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Updated Artist","status":"active"}'
```

## Tenant configuration API

### GET /api/admin/tenants/{id}/settings
Read all tenant settings visible through tenant admin settings/content screens. Requires `tenant:write` or `*`.

```bash
curl -s https://artsfol.io/api/admin/tenants/1/settings \
  -H 'Authorization: Bearer ACCESS_TOKEN'
```

### POST /api/admin/tenants/{id}/settings
Set tenant settings.

```bash
curl -s -X POST https://artsfol.io/api/admin/tenants/1/settings \
  -H 'Authorization: Bearer ACCESS_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{"settings":{"site_title":"Demo Artist","primary_color":"#111111","portfolio_sort":"manual"}}'
```

## Tenant content API

The collection routes below support `artworks`, `events`, `portfolio-sections`, `contact-messages`, and `email-signups`. They expose data that is otherwise managed through tenant admin UI screens.

### GET /api/admin/tenants/{id}/{entity}
List entity records for the tenant.

```bash
curl -s https://artsfol.io/api/admin/tenants/1/artworks \
  -H 'Authorization: Bearer ACCESS_TOKEN'
```

### POST /api/admin/tenants/{id}/{entity}
Create an entity record using JSON fields allowed for that entity.

```bash
curl -s -X POST https://artsfol.io/api/admin/tenants/1/events \
  -H 'Authorization: Bearer ACCESS_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{"title":"Open Studio","event_date":"2026-06-15","location":"Bucharest","event_type":"open_studio"}'
```

### POST /api/admin/tenants/{id}/{entity}/{item_id}
Update one entity record.

```bash
curl -s -X POST https://artsfol.io/api/admin/tenants/1/artworks/42 \
  -H 'Authorization: Bearer ACCESS_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{"title":"New title","status":"published","sort_order":10}'
```

### DELETE /api/admin/tenants/{id}/{entity}/{item_id}
Delete one entity record.

```bash
curl -s -X DELETE https://artsfol.io/api/admin/tenants/1/portfolio-sections/7 \
  -H 'Authorization: Bearer ACCESS_TOKEN'
```

## Browser admin flows

Platform admins manage users, tenants, jobs, stats, settings, routes, domains, audit logs, and email outbox under `/platform/admin/*`. User and tenant suspend/delete operations require JavaScript confirmation and CSRF tokens. Suspended tenant public resources render an ArtsFolio-branded unavailable page.

## Known auth limitation

Cookies cannot be shared directly between unrelated domains such as `bxiie.com` and `artsfol.io`. The patch normalizes sessions across `artsfol.io` and `*.artsfol.io`. Seamless movement from a tenant custom domain to `tenant.artsfol.io` requires an OAuth/OIDC or one-time signed handoff flow.

# End of file.
