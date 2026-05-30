# ArtsFolio HTTP and API Route Reference

This developer reference describes the practical browser and HTTP endpoints currently exposed by ArtsFolio. Use it as an implementation map for integrations, test scripts, and admin troubleshooting.

## Authentication

### GET `/login`
Renders the ArtsFolio login form. On tenant domains this is branded with the tenant name when possible.

```bash
curl -i https://artsfol.io/login
curl -i https://bxiie.com/login
```

### POST `/login`
Submits local email/password credentials. The response sets the `artsfolio_session` browser cookie. On `artsfol.io` and `*.artsfol.io`, the cookie is scoped to `.artsfol.io` so platform and tenant subdomains share the login. Custom domains still receive a host-scoped cookie because browsers cannot share cookies across unrelated registrable domains.

```bash
curl -i -X POST https://artsfol.io/login \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data 'csrf_token=TOKEN&email=admin@example.com&password=correct-horse-battery-staple'
```

### POST `/logout`
Revokes the current session and expires the browser cookie.

```bash
curl -i -X POST https://artsfol.io/logout \
  -H 'Cookie: artsfolio_session=SESSION' \
  --data 'csrf_token=TOKEN'
```

## Platform public routes

### GET `/`
Shows the ArtsFolio platform landing page. Signed-in users should see admin/account navigation rather than a sign-in button.

```bash
curl -i https://artsfol.io/
```

### GET `/directory`
Shows opted-in public tenant directory cards.

```bash
curl -i https://artsfol.io/directory
```

### GET `/developer` and `/help/developer`
Shows the developer reference for logged-in users. Anonymous users are redirected to login.

```bash
curl -i -H 'Cookie: artsfolio_session=SESSION' https://artsfol.io/developer
curl -i -H 'Cookie: artsfolio_session=SESSION' https://artsfol.io/help/developer
```

## Platform admin routes

### GET `/platform/admin/users`
Lists platform users, roles, login timestamps, and lifecycle state. Platform admins can rotate passwords, suspend, reactivate, or soft-delete users.

```bash
curl -i -H 'Cookie: artsfolio_session=SESSION' https://artsfol.io/platform/admin/users
```

### POST `/platform/admin/users/status`
Changes a user's lifecycle state. `suspended` and `deleted` immediately revoke active browser sessions.

```bash
curl -i -X POST https://artsfol.io/platform/admin/users/status \
  -H 'Cookie: artsfolio_session=SESSION' \
  --data 'csrf_token=TOKEN&user_id=123&status=suspended'
```

### GET `/platform/admin/tenants`
Lists tenants and current tenant lifecycle state.

```bash
curl -i -H 'Cookie: artsfolio_session=SESSION' https://artsfol.io/platform/admin/tenants
```

### POST `/platform/admin/tenants/status`
Changes tenant lifecycle state. Use `suspended` to temporarily hide tenant content and `archived` as the soft-delete state.

```bash
curl -i -X POST https://artsfol.io/platform/admin/tenants/status \
  -H 'Cookie: artsfolio_session=SESSION' \
  --data 'csrf_token=TOKEN&tenant_id=7&status=suspended'
```

### GET `/platform/admin/jobs`
Lists queued, running, completed, failed, and cancelled background jobs. If jobs stay queued, check `artsfolio-background-worker.service`.

```bash
curl -i -H 'Cookie: artsfolio_session=SESSION' 'https://artsfol.io/platform/admin/jobs?status=queued'
```

### POST `/platform/admin/jobs/action`
Requeues or cancels a background job.

```bash
curl -i -X POST https://artsfol.io/platform/admin/jobs/action \
  -H 'Cookie: artsfolio_session=SESSION' \
  --data 'csrf_token=TOKEN&job_id=42&job_admin_action=requeue'
```

## Tenant public routes

### GET `/`
Shows the tenant home page for an active tenant domain.

```bash
curl -i https://bxiie.com/
curl -i https://bxiie.artsfol.io/
```

### GET `/portfolio`, `/about`, `/contact`
Shows standard tenant content pages. Custom tenant slugs may also be configured.

```bash
curl -i https://bxiie.com/portfolio
curl -i https://bxiie.com/about
curl -i https://bxiie.com/contact
```

### Suspended tenant request
Visitors hitting a suspended or archived tenant domain receive an ArtsFolio-branded unavailable page with HTTP 503.

```bash
curl -i https://suspended-tenant.example/
```

## Tenant admin routes

### GET `/admin`
Shows the tenant admin dashboard. Requires an active session and tenant admin/owner role.

```bash
curl -i -H 'Cookie: artsfolio_session=SESSION' https://bxiie.com/admin
```

### GET `/admin/settings`
Shows tenant settings and CSS controls.

```bash
curl -i -H 'Cookie: artsfolio_session=SESSION' https://bxiie.com/admin/settings
```

### GET `/admin/stats`
Shows tenant analytics.

```bash
curl -i -H 'Cookie: artsfolio_session=SESSION' https://bxiie.com/admin/stats
```

# End of file.
