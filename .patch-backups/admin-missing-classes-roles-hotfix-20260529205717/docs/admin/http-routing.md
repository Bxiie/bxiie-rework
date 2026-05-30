# HTTP Routing Administration

## Current public surfaces

```text
artsfol.io              platform marketing surface
app.artsfol.io          future platform and tenant admin surface
{tenant}.artsfol.io     default tenant public surface
custom domains          paid tenant public surface
```

## Current implemented route split

If the request host resolves to a tenant, tenant public routes are used.

If the request host does not resolve to a tenant, platform marketing routes are used.

## Current platform routes

```text
GET /
GET /pricing
GET /signup
GET /login
```

## Current tenant routes

```text
GET /
GET /portfolio
GET /about
GET /contact
```

## Current safety note

This routing layer does not write infrastructure files, alter Apache, run Certbot, or change DNS.

<!-- End of file. -->
