# HTTP Smoke Tests Administration

## Current local command

```bash
./scripts/test/http_smoke.sh
```

## Purpose

Catches route registration errors across platform and tenant host contexts.

## Production deployment note

This local smoke test should evolve into deployment checks for:

```text
artsfol.io
app.artsfol.io
tenant subdomains
custom tenant domains
API auth routes
admin routes
```

<!-- End of file. -->
