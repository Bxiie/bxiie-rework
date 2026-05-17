
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

## 2026-05-16 10:05 Europe/Bucharest

- Added identity and membership foundation.
- Confirmed authentication posture: OAuth/OIDC and local email/password are both supported.
- Confirmed API authentication posture: OAuth2 bearer tokens.
- Added user_identities, tenant_memberships, roles, role_assignments, password_reset_tokens, and email_verification_tokens schema.
- Added role seed data for platform and tenant scopes.
- Added user, identity, password hashing, membership repository, and membership service classes.
- Added manual verification scripts for auth architecture and identity/membership behavior.
- Added dev, admin, user, and auth template documentation for identity and membership.

## 2026-05-16 10:25 Europe/Bucharest

- Added local email/password authentication service foundation.
- Added session token generation and hashed session persistence.
- Added manual verification script for password registration, login, and active session lookup.
- Documented local password authentication for dev, admin, and user audiences.
- Authentication posture remains: OAuth/OIDC and local email/password are both supported; APIs use OAuth2 bearer tokens.

## 2026-05-16 10:40 Europe/Bucharest

- Added missing user_sessions migration required by local password authentication.
- Verified password auth depends on identity/membership schema plus user_sessions.

## 2026-05-16 10:55 Europe/Bucharest

- Added browser session cookie support for local email/password authentication.
- Added CurrentUser middleware for resolving artsfolio_session cookies.
- Added PasswordAuthController with login form, password login, current-user page, and logout.
- Added simple PHP-session-backed CSRF token service.
- Added POST route support to the router.
- Added browser session auth documentation for dev and admin audiences.
- OAuth/OIDC and local email/password remain supported authentication models; APIs use OAuth2 bearer tokens.

## 2026-05-16 11:10 Europe/Bucharest

- Repaired Router.php after it was accidentally overwritten with front-controller/bootstrap logic.
- Restored public/index.php as the only HTTP front controller requiring bootstrap/app.php.
- Verified router/front-controller separation.

## 2026-05-16 11:20 Europe/Bucharest

- Added missing App\Http\RouteMatch value object required by parameterized router.
- Refreshed Composer autoload after adding RouteMatch.

## 2026-05-16 11:35 Europe/Bucharest

- Added OAuth2 bearer token repository and service.
- Added API bearer token middleware.
- Added GET /api/me endpoint using bearer-token authentication.
- Added development script for creating a temporary OAuth access token.
- Documented API bearer authentication for dev and admin audiences.
- UI authentication remains OAuth/OIDC and local email/password; API authentication uses OAuth2 bearer tokens.

## 2026-05-16 13:50 Europe/Bucharest

- Added missing OAuth token schema required by API bearer authentication.
- Added oauth_clients, oauth_access_tokens, and oauth_refresh_tokens tables.
- Verified API bearer token development script depends on this schema.

## 2026-05-16 14:05 Europe/Bucharest

- Added RequireScope middleware for OAuth2 bearer-token scope checks.
- Updated GET /api/me to require api:read.
- Added manual scope verification script.
- Added API scope documentation for dev and admin audiences.

## 2026-05-16 14:55 Europe/Bucharest

- Added missing TenantMeController for tenant-scoped GET /api/me.
- Added missing RequireTenantAccess middleware.
- Refreshed Composer autoload after adding API tenant classes.

## 2026-05-16 15:10 Europe/Bucharest

- Added RequireTenantRole middleware for tenant membership/role enforcement.
- Updated tenant GET /api/me to optionally enforce tenant roles when middleware is provided.
- Wired tenant API route to enforce owner, admin, editor, or viewer membership.
- Added manual tenant role API access verification script.
- Added dev/admin documentation for tenant role API access.

## 2026-05-16 15:25 Europe/Bucharest

- Added AuditLogRepository for structured audit log writes.
- Added manual audit log verification script.
- Added dev/admin documentation for API audit logging.

## 2026-05-16 15:40 Europe/Bucharest

- Wired AuditLogRepository into tenant GET /api/me controller path.
- Tenant API denied-access paths now record audit_log rows when missing token, missing scope, wrong tenant token, or missing tenant membership role occurs.
- Added manual script to inspect recent tenant API denial audit records.
- Added development documentation for API denial audit flow.

## 2026-05-16 15:55 Europe/Bucharest

- Added audit logging to local password login and logout controller paths.
- Added audit records for invalid CSRF, failed login, successful login, invalid logout CSRF, and successful logout.
- Added auth audit inspection script.
- Added dev/admin documentation for auth audit logging.

## 2026-05-16 16:10 Europe/Bucharest

- Added local password reset backend foundation.
- Added hashed password reset token repository and reset service.
- Added manual password reset verification script.
- Added dev/admin/user documentation and password reset email template.
- Password reset UI and email delivery are not wired yet.

## 2026-05-16 16:25 Europe/Bucharest

- Added local account email verification backend foundation.
- Added hashed email verification token repository and verification service.
- Added manual email verification script.
- Added dev/admin/user documentation and verification email template.
- Email delivery and browser verification routes are not wired yet.

## 2026-05-16 16:45 Europe/Bucharest

- Added email_outbox table for queued outbound email.
- Added EmailOutboxRepository, TemplateRenderer, and LifecycleEmailService.
- Added queued password reset, email verification, and welcome email support.
- Added welcome lifecycle template.
- Added manual email outbox verification script.
- Added dev/admin/user email outbox documentation.
- Email delivery provider and worker are not wired yet.

## 2026-05-16 17:00 Europe/Bucharest

- Added dry-run email worker for email_outbox.
- Added claimNext support for queued email rows.
- Added DryRunEmailSender.
- Added email outbox status inspection script.
- Added dev/admin documentation for email worker behavior.
- Email delivery remains non-destructive and does not contact SMTP or external providers.

## 2026-05-16 17:20 Europe/Bucharest

- Added EmailSenderInterface.
- Updated DryRunEmailSender to implement sender contract.
- Added basic SmtpEmailSender for Mailhog-style local SMTP testing.
- Added EmailSenderFactory using EMAIL_DRIVER.
- Updated email worker to use configured sender.
- Added sender factory verification script.
- Added dev/admin documentation for email sender selection.

## 2026-05-16 17:40 Europe/Bucharest

- Added tenant contact notification service.
- Added tenant email signup notification service.
- Notifications queue email_outbox rows for tenant site_admin_email.
- Added manual tenant notification verification script.
- Added tenant notification templates and dev/admin/user documentation.

## 2026-05-16 18:00 Europe/Bucharest

- Added contact_messages and email_signups tables.
- Added ContactMessageRepository and EmailSignupRepository.
- Added ContactMessageService and EmailSignupService to persist records before queueing notifications.
- Added manual persistence verification script.
- Added dev/admin/user documentation for contact messages and email signups.

## 2026-05-16 18:20 Europe/Bucharest

- Added tenant public ContactController and SignupController.
- Added POST /contact and POST /signup tenant routes.
- Updated tenant placeholder home/contact pages to render CSRF-protected forms.
- Public contact submissions now persist contact messages and queue notifications.
- Public signup submissions now persist email signups and queue notifications.
- Added dev/admin/user documentation for public contact and signup routes.

## 2026-05-16 18:40 Europe/Bucharest

- Added rate_limits table.
- Added database-backed fixed-window RateLimiter.
- Added rate limiting to tenant public contact and signup POST routes.
- Added manual rate limiter verification script.
- Added dev/admin documentation for rate limiting.

## 2026-05-16 18:50 Europe/Bucharest

- Repaired local migration bookkeeping after 0009_contact_signup_records.sql had created tables without being recorded in schema_migrations.
- Applied 0010_rate_limits.sql successfully after marking 0009 as applied.

## 2026-05-16 19:05 Europe/Bucharest

- Added migration integrity checker.
- Checker compares schema_migrations records against expected tables introduced by known migrations.
- Added dev/admin documentation for migration integrity checks and repair pattern.

## 2026-05-16 19:20 Europe/Bucharest

- Added scripts/test/preflight.sh.
- Preflight runs PHP syntax checks, migration integrity checks, and core smoke tests.
- Added dev/admin documentation for local preflight workflow.

## 2026-05-16 19:50 Europe/Bucharest

- Added HTTP smoke test runner.
- Smoke test starts local PHP dev server, checks platform routes, tenant routes, and unauthorized API behavior.
- Added HTTP smoke test to preflight when available.
- Added dev/admin documentation for HTTP smoke tests.

## 2026-05-16 20:05 Europe/Bucharest

- Added platform admin role middleware.
- Added protected placeholder GET /admin platform route.
- Added platform admin dashboard placeholder controller.
- Added manual platform role verification script.
- Added dev/admin documentation for platform admin routes.

## 2026-05-16 20:15 Europe/Bucharest

- Added missing MembershipRepository::platformRolesForUser required by platform admin role middleware.

## 2026-05-16 20:25 Europe/Bucharest

- Added tenant admin browser role middleware.
- Added protected placeholder tenant GET /admin route.
- Added tenant admin dashboard placeholder controller.
- Added manual tenant role verification script.
- Added dev/admin documentation for tenant admin routes.
- Tenant admin remains separate from platform admin.

## 2026-05-16 20:45 Europe/Bucharest

- Added tenant admin settings controller.
- Added tenant GET/POST /admin/settings routes.
- Added editable tenant site_title and site_admin_email settings.
- Tenant settings route requires tenant owner or tenant admin role.
- Added manual tenant settings verification script.
- Added dev/admin/user documentation for tenant admin settings.

## 2026-05-16 21:05 Europe/Bucharest

- Added platform_settings table.
- Added PlatformSettingsRepository.
- Added platform admin settings controller.
- Added platform GET/POST /admin/platform-settings routes.
- Added editable platform_name, support_email, and expected_ipv4 settings.
- Platform settings require platform owner/admin role.
- Added dev/admin/user documentation for platform settings.

## 2026-05-16 21:25 Europe/Bucharest

- Added TenantAdminRepository for platform tenant list reads.
- Added platform admin tenant list screen.
- Added platform admin email outbox list screen.
- Added dashboard links for tenants and email outbox.
- Added manual platform admin list verification script.
- Added dev/admin documentation for platform admin lists.

## 2026-05-16 21:45 Europe/Bucharest

- Added platform admin audit log list screen.
- Added GET /admin/audit-log route.
- Added dashboard link for audit log.
- Added manual audit log list verification script.
- Added dev/admin documentation for platform audit log screen.

## 2026-05-16 22:00 Europe/Bucharest

- Added tenant admin contact messages list screen.
- Added tenant admin email signups list screen.
- Added tenant admin dashboard links for contact messages and email signups.
- Added manual tenant admin list verification script.
- Added dev/admin/user documentation for tenant admin lists.

## 2026-05-16 22:15 Europe/Bucharest

- Updated ContactMessageRepository to populate required contact_messages.uuid during inserts.

## 2026-05-16 22:25 Europe/Bucharest

- Added CSV response helper.
- Added tenant admin contact messages CSV export.
- Added tenant admin email signups CSV export.
- Added export links to tenant admin list screens.
- Added dev/admin/user documentation for tenant CSV exports.

## 2026-05-16 22:35 Europe/Bucharest

- Fixed CSV helper for PHP 8.5 fputcsv escape argument.
- Fixed CSV smoke test to capture Response::send output instead of calling nonexistent Response::body().

## 2026-05-16 22:45 Europe/Bucharest

- Added contact message status update repository method.
- Added tenant admin POST /admin/contact-messages/status route.
- Added read/archive/spam actions to contact message admin list.
- Added manual contact message status verification script.
- Added dev/admin/user documentation for contact message status.

## 2026-05-16 23:05 Europe/Bucharest

- Added email signup consent status update repository method.
- Added tenant admin POST /admin/email-signups/consent route.
- Added confirm/unsubscribe actions to email signup admin list.
- Added manual email signup consent verification script.
- Added dev/admin/user documentation for email signup consent.

## 2026-05-16 23:20 Europe/Bucharest

- Added tenant admin action audit logging hooks.
- Contact message status changes can write tenant.contact_message.status_changed.
- Email signup consent changes can write tenant.email_signup.consent_changed.
- Wired AuditLogRepository into tenant admin status/consent controllers.
- Added manual tenant admin action audit inspection script and documentation.

## 2026-05-16 23:35 Europe/Bucharest

- Added tenant settings audit logging hook.
- Tenant admin POST /admin/settings can now write tenant.settings.updated audit rows.
- Wired AuditLogRepository into tenant settings controller route construction.
- Added manual tenant settings audit inspection script and documentation.

## 2026-05-16 23:50 Europe/Bucharest

- Added platform settings audit logging hook.
- Platform admin POST /admin/platform-settings can now write platform.settings.updated audit rows.
- Wired AuditLogRepository into platform settings controller route construction.
- Added manual platform settings audit inspection script and documentation.

## 2026-05-17 00:05 Europe/Bucharest

- Added AuditLogRepository::search.
- Added platform audit log filters for action, tenant ID, and user ID.
- Updated platform audit log screen with filter form.
- Added manual audit log search verification script and documentation.

## 2026-05-17 00:20 Europe/Bucharest

- Added platform audit log CSV export route.
- Export supports the same action, tenant_id, and user_id filters as the audit log screen.
- Added dev/admin documentation for platform audit log CSV export.

## 2026-05-17 00:40 Europe/Bucharest

- Added tenant admin audit log screen.
- Added tenant admin audit log CSV export.
- Tenant audit log is forced to the current tenant ID.
- Added tenant admin dashboard link for audit log.
- Added manual tenant audit log verification script and documentation.

## 2026-05-17 00:55 Europe/Bucharest

- Expanded local preflight runner to include recent platform/tenant admin, audit, CSV, contact/signup, and consent smoke tests.
- Added shell syntax checks for scripts.
- Kept missing optional tests as skips rather than hard failures.
- Refreshed preflight documentation.

## 2026-05-17 01:10 Europe/Bucharest

- Added shared admin CSS at public/assets/css/admin.css.
- Added AdminLayout helper for future admin screen rendering.
- Added manual admin layout verification script.
- Added dev/admin/user documentation for admin layout foundation.

## 2026-05-17 01:25 Europe/Bucharest

- Migrated platform admin dashboard to shared AdminLayout.
- Migrated tenant admin dashboard to shared AdminLayout.
- Added dashboard layout smoke test and documentation.

## 2026-05-17 01:40 Europe/Bucharest

- Migrated platform tenant list screen to shared AdminLayout.
- Migrated platform email outbox list screen to shared AdminLayout.
- Migrated platform audit log screen to shared AdminLayout.
- Added platform admin layout smoke test and documentation.

## 2026-05-17 01:55 Europe/Bucharest

- Migrated tenant contact messages screen to shared AdminLayout.
- Migrated tenant email signups screen to shared AdminLayout.
- Migrated tenant audit log screen to shared AdminLayout.
- Added tenant admin layout smoke test and documentation.

## 2026-05-17 02:10 Europe/Bucharest

- Migrated platform settings form to shared AdminLayout.
- Migrated tenant settings form to shared AdminLayout.
- Added settings layout smoke test and documentation.

## 2026-05-17 02:25 Europe/Bucharest

- Added Pagination helper.
- Added offset support to audit log, contact message, and email signup repository reads.
- Added basic pagination to platform audit log, tenant audit log, tenant contact messages, and tenant email signups.
- Added pagination smoke test and documentation.

## 2026-05-17 02:40 Europe/Bucharest

- Added session-backed FlashMessages helper.
- Updated AdminLayout to render flash messages.
- Added admin CSS for success/error flash messages.
- Added success flashes for contact message status, email signup consent, tenant settings, and platform settings actions.
- Added flash message smoke test and documentation.

## 2026-05-17 03:00 Europe/Bucharest

- Added platform admin route map page.
- Added tenant admin route map page.
- Added /admin/routes routes for platform and tenant admin contexts.
- Added route map smoke test and documentation.

## 2026-05-17 03:15 Europe/Bucharest

- Added platform custom domain admin repository.
- Added platform GET /admin/domains screen.
- Added Domains nav link to platform admin dashboard and route map.
- Added manual platform domain list verification script and documentation.

## 2026-05-17 03:15 Europe/Bucharest

- Added platform custom domain admin repository.
- Added platform GET /admin/domains screen.
- Added Domains nav link to platform admin dashboard and route map.
- Added manual platform domain list verification script and documentation.

## 2026-05-17 03:30 Europe/Bucharest

- Added DomainAdminService for platform custom domain maintenance actions.
- Added platform POST /admin/domains/action route.
- Added Verify DNS and Render vhost actions to platform custom domain list.
- Domain actions queue existing background job types.
- Added audit logging and flash messages for queued domain actions.
- Added manual platform domain action verification script and documentation.
