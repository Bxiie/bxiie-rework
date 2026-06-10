# Domain Automation Administration

## Scope

This document describes the current custom-domain automation workflow for ArtsFolio platform administrators.

The current implementation is intentionally non-destructive. It does not write Apache configuration files, enable Apache sites, reload Apache, or invoke Certbot.

## Current domain lifecycle

Tenant custom domains currently move through these states:

```text
pending_dns
dns_verified
vhost_pending
cert_pending
active
failed
disabled
```

Current implemented behavior:

```text
pending_dns -> dns_verified
dns_verified -> vhost_pending
```

Future production behavior will add:

```text
vhost_pending -> cert_pending -> active
```

## Platform roles

Platform administrators may:

```text
create tenant domain records
inspect DNS verification results
inspect queued domain jobs
review rendered Apache vhost artifacts
approve rendered artifacts
queue dry-run write planning jobs
```

Platform administrators must not yet:

```text
write files under /etc/apache2
run a2ensite from the app
reload apache2 from the app
invoke certbot from the app
mark a domain active without a validated certificate workflow
```

## Tenant roles

Tenant administrators may eventually:

```text
request a custom domain
view DNS setup instructions
see verification status
retry DNS verification
select a primary domain
```

Tenant administrators must not:

```text
approve Apache artifacts
write server configs
reload services
invoke Certbot
modify platform infrastructure settings
```

## Custom-domain request flow

A tenant custom-domain request should create a domain row and queue DNS verification.

```bash
php scripts/test/domain_automation_queue.php bxiie.com example-artist.com
```

Expected local behavior:

```text
tenant_domains.status = pending_dns
background_jobs.job_type = custom_domain.verify_dns
```

## DNS verification

DNS verification is read-only. It checks A records against configured expected IPv4 addresses.

Configuration placeholder:

```text
ARTSFOLIO_EXPECTED_IPV4=127.0.0.1
```

Manual check:

```bash
php scripts/test/verify_dns.php example-artist.com 127.0.0.1
```

If DNS does not match, the domain remains:

```text
pending_dns
```

If DNS matches, the domain becomes:

```text
dns_verified
```

and the system may queue:

```text
custom_domain.render_vhost
```

## Rendered vhost artifacts

Rendered Apache vhost text is stored in:

```text
domain_artifacts
```

Artifact type:

```text
apache_http_vhost
```

Rendered artifacts start with status:

```text
rendered
```

Inspect latest artifact:

```bash
php scripts/test/domain_artifacts.php artifact-test.example
```

## Artifact approval

Platform administrators can approve the latest rendered artifact for a hostname.

```bash
php scripts/test/approve_domain_artifact.php artifact-test.example
```

Approval changes status:

```text
rendered -> approved
```

## Dry-run write planning

Approved artifacts can be used to generate a dry-run write plan.

```bash
php scripts/test/queue_write_approved_vhost.php bxiie.com artifact-test.example true
php scripts/workers/run_once.php
```

Expected dry-run output includes:

```text
target_path
enable_command
reload_command
artifact_body
```

Example target path:

```text
/etc/apache2/sites-available/artsfolio-tenant-artifact-test.example.conf
```

This is only a plan. No file is written.

## Explicitly blocked behavior

The current worker must reject real writes.

```bash
php scripts/test/queue_write_approved_vhost.php bxiie.com artifact-test.example false
php scripts/workers/run_once.php
```

Expected failure:

```text
Real Apache vhost writes are not implemented. Requeue with dry_run=true.
```

## Job inspection

Inspect recent background jobs:

```bash
php scripts/test/job_status.php
```

Useful query:

```bash
docker exec -i artsfolio-mariadb mariadb -u artsfolio -partsfolio_dev artsfolio <<'SQL'
SELECT id, tenant_id, job_type, status, attempts, payload, completed_at, failed_at, last_error
FROM background_jobs
ORDER BY id DESC
LIMIT 20;
SQL
```

## Recovery notes

To remove test jobs and domains:

```bash
docker exec -i artsfolio-mariadb mariadb -u artsfolio -partsfolio_dev artsfolio <<'SQL'
DELETE FROM background_jobs
WHERE payload LIKE '%artifact-test.example%';

DELETE FROM tenant_domains
WHERE hostname = 'artifact-test.example';

DELETE FROM domain_artifacts
WHERE hostname = 'artifact-test.example';
SQL
```

To reset only the local development database:

```bash
docker compose down -v
docker compose up -d
php scripts/database/migrate.php
```

This is destructive for local ArtsFolio data only.

## Future production requirements

Before enabling real Apache writes, add:

```text
APP_ENV=production requirement
separate privileged infrastructure worker
managed config output directory
config validation before enablement
atomic write strategy
rollback copy of previous config
apachectl configtest
a2ensite execution logging
systemctl reload apache2 logging
Certbot issuance and renewal checks
domain active transition only after TLS success
```

## Current safety boundary

Current domain automation remains local-development-safe and non-destructive.

It may:

```text
queue jobs
verify DNS read-only
render config text
store artifacts
approve artifacts
plan writes
```

It must not:

```text
write Apache configs
enable sites
reload services
issue certificates
touch production infrastructure
```

## Caddy on-demand TLS note

Current ArtsFolio production uses Caddy on-demand TLS. DNS verification is the active domain automation path. A successful `custom_domain.verify_dns` job marks the tenant domain `active`; Caddy then authorizes the hostname through `/caddy/ask`. The old Apache `custom_domain.render_vhost` flow is deprecated and should not be exposed as a normal admin action.

<!-- End of file. -->
