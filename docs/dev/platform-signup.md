# Platform Signup

## Purpose

The platform signup flow creates a tenant from the platform domain.

## Routes

```text
GET  /signup
POST /signup
```

## Current behavior

`POST /signup` creates:

```text
tenant
platform subdomain tenant domain
admin user/password identity when supported by schema
tenant membership
tenant admin role assignment
provisioning background jobs
lifecycle email outbox rows
```

The request redirects to:

```text
https://<slug>.artsfol.io/login
```

## Operational note

Provisioning is queued through `background_jobs`. Request handling should not perform expensive domain or deployment work directly.

<!-- End of file. -->

## Tenant slug availability validation

Public signup normalizes the requested site slug, then checks `tenants.slug`
before creating any tenant records. A duplicate slug returns the user-facing
message `That site address is already in use. Please choose another.` The
`tenants.slug` unique key remains the final protection against concurrent
requests selecting the same slug.

When signup returns HTTP 422 with an undefined `ensureTenantSlugAvailable()`
method, verify `app/Platform/Signup/TenantSignupService.php` contains that
private method and run:

```bash
php -l app/Platform/Signup/TenantSignupService.php
php scripts/test/tenant_signup_slug_availability_static.php
```

<!-- End of tenant slug availability validation. -->

