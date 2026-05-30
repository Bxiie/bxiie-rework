# Production-Safe Test Scripts

Deploy preflight must not mutate live production tenant data.

Any script under `scripts/test` that creates rows or updates tenant data must include:

```php
require_once __DIR__ . '/TestEnvironment.php';
TestEnvironment::skipIfProduction(basename(__FILE__));
```

Production is detected from:

```text
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env
```

Mutating tests should use isolated test tenants locally and skip against production.
