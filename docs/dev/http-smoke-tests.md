# HTTP Smoke Tests

## Scope

The HTTP smoke test starts the PHP development server on a temporary local port, checks platform and tenant routes, then shuts the server down.

## Command

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
./scripts/test/http_smoke.sh
```

## Default port

```text
18080
```

Override:

```bash
ARTSFOLIO_HTTP_SMOKE_PORT=18081 ./scripts/test/http_smoke.sh
```

## Current checks

```text
artsfol.io /
artsfol.io /pricing
artsfol.io /login
bxiie.com /
bxiie.com /contact
bxiie.com /portfolio
bxiie.com /api/me without token returns 401
artsfol.io /api/me without token returns 401
```

## Preflight

The HTTP smoke test is included in:

```bash
./scripts/test/preflight.sh
```

<!-- End of file. -->
