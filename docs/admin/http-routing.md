# Routing operations

Phase 8 separates platform and tenant route registration while preserving the existing URLs. Deployment and smoke tests should exercise both platform-host and tenant-host routes.

When a route intentionally changes, run:

```bash
php scripts/test/route_inventory.php > scripts/test/fixtures/route_inventory.json
php scripts/test/phase8_routing_static.php
```

Unexpected snapshot changes should be treated as a routing regression.
