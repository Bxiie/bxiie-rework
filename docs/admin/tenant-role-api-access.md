# Tenant Role API Access Administration

## Current model

Tenant API access requires both:

```text
valid bearer token
tenant membership role
```

## Current allowed roles for GET /api/me

```text
owner
admin
editor
viewer
```

## Production requirements

Before production launch:

```text
define route-by-route role matrix
add audit logs for denied tenant API access
add admin UI for tenant membership review
add role assignment change history
```

<!-- End of file. -->
