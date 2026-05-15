
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
