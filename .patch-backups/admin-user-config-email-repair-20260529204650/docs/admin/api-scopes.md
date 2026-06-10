# API Scope Administration

## Current model

API requests use OAuth2 bearer tokens and route-level scope checks.

## Current scope

```text
api:read
```

## Production requirements

Before production launch:

```text
define canonical scope list
add scope assignment UI for OAuth clients
add scope enforcement middleware per API route
audit denied API requests
document tenant-scoped token behavior
```

<!-- End of file. -->
