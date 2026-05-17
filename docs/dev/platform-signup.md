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
