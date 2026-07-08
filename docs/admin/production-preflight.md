# Production Preflight

Production preflight may check syntax, migration integrity, service health, and non-mutating HTTP smoke behavior.

It must not create or update:

```text
tenant_settings
email_signups
contact_messages
audit_log
tenant memberships
```

Mutating test scripts must skip when the production env file is active.

## Go-live gate

Before public signup, live checkout, or paid subscriptions are enabled, run the production preflight from the production tree and confirm every route below loads without a 500 response.

```bash
cd /var/www/artsfolio

set -a
. /etc/artsfolio/artsfolio.env
set +a

php scripts/database/check_migration_integrity.php
php scripts/database/check_schema_health.php
bash scripts/test/preflight.sh
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env ./scripts/deploy/healthcheck.sh
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php scripts/ops/monitor_artsfolio.php
```

Browser smoke routes:

```text
https://artsfol.io/
https://artsfol.io/login
https://artsfol.io/platform/admin
https://artsfol.io/platform/admin/operations
https://artsfol.io/platform/admin/jobs
https://bxiie.com/
https://bxiie.com/login
https://bxiie.com/admin
https://bxiie.com/admin/artworks
https://bxiie.com/admin/settings
https://bxiie.com/cart
```

The platform job detail route is covered by `scripts/test/platform_job_detail_route_params_static.php` because the router passes parameterized route values as an associative array. Do not change `/platform/admin/jobs/{id}` back to a scalar route argument.

<!-- End of file. -->
