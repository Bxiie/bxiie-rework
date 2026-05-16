
## 2026-05-15

- Added tenant resolver and request-scoped tenant context.
- Added MariaDB migration runner adjusted for MariaDB DDL implicit commits.
- Added Dockerized ArtsFolio MariaDB on local port 3307.
- Added local storage abstraction foundation for future tenant-isolated media.

- Added TenantStoragePaths for tenant-isolated media path generation.
- Tenant media paths now follow: tenants/{tenant_slug}/{area}/{filename}.

- Added MediaAssetRepository for tenant-scoped media database records.
- Added manual media asset creation verification script.

- Recreated untracked manual media asset verification script using project UUID helper.

- Added MediaAssetService to coordinate tenant-isolated file storage and media asset database persistence.
- Added manual verification for storing media content and creating a media_assets row.

- Added ArtworkRepository for tenant-scoped artwork persistence.
- Added manual verification script for creating artwork linked to latest tenant media asset.

- Added PortfolioSectionRepository for tenant-scoped portfolio sections.
- Added artwork-to-section assignment support for multi-section portfolio membership.
- Added manual verification script for creating sections and assigning latest artwork.

- Added TenantSettingsRepository for tenant-scoped client settings.
- Added manual verification script for tenant setting reads and upserts.

- Added PlatformSettingsRepository for parent/platform settings.
- Added manual verification script for platform setting reads and upserts.
- Platform settings and tenant/client settings now have separate repository boundaries.

- Added TenantDomainRepository for platform subdomain and custom-domain persistence.
- Added manual verification script for tenant domain creation, status updates, and listing.

## 2026-05-15 14:30 Europe/Bucharest

- Added BackgroundJobRepository for queued platform jobs.
- Added background_jobs schema if missing from earlier migrations.
- Added manual verification script for enqueue, claim, and complete behavior.
- Background jobs will support later custom-domain DNS validation, Apache vhost automation, Certbot automation, image processing, email, and analytics rollups.

## 2026-05-15 14:45 Europe/Bucharest

- Added ApacheVhostRenderer for dry-run tenant custom-domain vhost rendering.
- Vhost rendering currently outputs HTTP-only redirect vhost text and does not write files, reload Apache, or request certificates.
- Custom-domain automation remains intentionally non-destructive at this stage.

## 2026-05-15 15:00 Europe/Bucharest

- Added DomainAutomationService for non-destructive custom-domain automation queueing.
- Added manual verification script for custom-domain DNS verification and vhost render job creation.
- Domain automation still does not write Apache configs, reload Apache, or invoke Certbot.

## 2026-05-15 16:00 Europe/Bucharest

- Added read-only DNS verifier for tenant custom-domain A-record checks.
- Updated DNS verification job handler to return actual and expected IPv4 address details.
- Added ARTSFOLIO_EXPECTED_IPV4 environment variable placeholder.

## 2026-05-15 16:40 Europe/Bucharest

- Verified custom-domain automation ordering fix.
- Non-verifying domains remain pending_dns after DNS check.
- Non-verifying domains do not queue custom_domain.render_vhost jobs.

## 2026-05-15 16:55 Europe/Bucharest

- Added domain_artifacts table for inspectable generated domain automation artifacts.
- Added DomainArtifactRepository.
- Updated render-vhost job handler to store rendered Apache vhost text before any future file writes.
- Domain automation remains non-destructive: no Apache config writes, no Apache reloads, no Certbot calls.

## 2026-05-15 17:10 Europe/Bucharest

- Added approval transition for rendered domain artifacts.
- Added manual script to approve the latest rendered artifact for a hostname.
- Domain automation still does not write Apache configs, reload Apache, or invoke Certbot.

## 2026-05-15 17:25 Europe/Bucharest

- Added ApacheVhostWritePlanner for dry-run approved vhost write planning.
- Added WriteApprovedVhostJobHandler for non-destructive write planning.
- Added queue script for custom_domain.write_approved_vhost jobs.
- Vhost write flow still does not write files, enable Apache sites, reload Apache, or invoke Certbot.

## 2026-05-15 17:40 Europe/Bucharest

- Added AppEnvironment helper for runtime environment safety checks.
- Future infrastructure-mutating workflows can require APP_ENV=production before writing Apache configs, reloading services, or invoking Certbot.
- Current development remains local on /Users/bxiie/Dropbox/tcdev/artsfolio.

## 2026-05-15 17:50 Europe/Bucharest

- Added explicit dry_run payload handling to approved vhost write jobs.
- custom_domain.write_approved_vhost refuses real Apache writes because production writer is not implemented yet.
- Local development remains non-destructive on the dev workstation.

## 2026-05-16 09:00 Europe/Bucharest

- Added first HTTP front controller at public/index.php.
- Added minimal Request, Response, and Router classes.
- Added tenant resolution middleware for Host-header based routing.
- Added placeholder platform marketing routes.
- Added placeholder tenant public routes.
- Added development, admin, and user documentation for HTTP routing.
- HTTP routing remains local-development safe and does not mutate infrastructure.
