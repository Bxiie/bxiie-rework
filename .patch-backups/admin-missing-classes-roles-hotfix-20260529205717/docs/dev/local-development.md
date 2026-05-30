# Local Development

## Project root

```text
/Users/bxiie/Dropbox/tcdev/artsfolio
```

## Local database

```text
Container: artsfolio-mariadb
Image: mariadb:11.4
Host port: 3307
Container port: 3306
Database: artsfolio
Username: artsfolio
Password: artsfolio_dev
Root password: artsfolio_root_dev
```

TinyLimo uses local port `3306`, so ArtsFolio uses `3307`.

## Start services

```bash
docker compose up -d
```

## Run migrations

```bash
php scripts/database/migrate.php
```

## Seed data

```bash
docker exec -i artsfolio-mariadb mariadb -u artsfolio -partsfolio_dev artsfolio < database/seeds/0001_plans.sql
docker exec -i artsfolio-mariadb mariadb -u artsfolio -partsfolio_dev artsfolio < database/seeds/0002_bxiie_tenant.sql
```

## Tenant checks

```bash
php scripts/test/resolve_tenant.php bxiie.com
php scripts/test/resolve_tenant.php bxiie.artsfol.io
php scripts/test/resolve_tenant.php artsfol.io
```

Expected:

```text
bxiie.com resolves to bxiie
bxiie.artsfol.io resolves to bxiie
artsfol.io does not resolve to a tenant
```

## Worker

```bash
php scripts/workers/run_once.php
php scripts/test/job_status.php
```

## Domain automation safety

Current domain automation is non-destructive.

Allowed:

```text
create tenant domain records
queue background jobs
verify DNS read-only
render Apache vhost text
store rendered vhost artifacts
approve rendered artifacts
produce dry-run write plans
```

Not allowed yet:

```text
write files under /etc/apache2
run a2ensite
reload apache2
invoke certbot
mutate production server infrastructure
```

## Dry-run custom-domain flow

```bash
php scripts/test/domain_automation_queue.php bxiie.com example-artist.com
php scripts/workers/run_once.php
```

Manually queue render test:

```bash
docker exec -i artsfolio-mariadb mariadb -u artsfolio -partsfolio_dev artsfolio <<'SQL'
INSERT INTO background_jobs (tenant_id, job_type, payload, status)
SELECT id, 'custom_domain.render_vhost', JSON_OBJECT('hostname', 'artifact-test.example', 'document_root', '/var/www/artsfolio/public'), 'queued'
FROM tenants
WHERE slug = 'bxiie';
SQL
```

Then:

```bash
php scripts/workers/run_once.php
php scripts/test/domain_artifacts.php artifact-test.example
php scripts/test/approve_domain_artifact.php artifact-test.example
php scripts/test/queue_write_approved_vhost.php bxiie.com artifact-test.example true
php scripts/workers/run_once.php
```

## Environment guard

```bash
APP_ENV=local php scripts/test/app_environment.php
APP_ENV=production php scripts/test/app_environment.php
```

## Reset local database

Destructive for local ArtsFolio data only:

```bash
docker compose down -v
docker compose up -d
php scripts/database/migrate.php
```

<!-- End of file. -->
