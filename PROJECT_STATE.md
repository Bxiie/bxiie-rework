
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

## 2026-05-17 03:45 Europe/Bucharest

- Added JobAdminRepository for platform background job reads.
- Added platform GET /admin/jobs screen.
- Added status and job_type filters for background jobs.
- Added Jobs links to platform dashboard and route map.
- Added manual background job list verification script and documentation.

## 2026-05-17 04:00 Europe/Bucharest

- Added JobAdminService for platform background job actions.
- Added POST /admin/jobs/action route.
- Added Requeue and Cancel buttons to platform background job list.
- Added flash messages and audit events for job actions.
- Added manual platform job action verification script and documentation.

## 2026-05-17 04:10 Europe/Bucharest

- Adjusted platform background job cancel action to mark queued jobs failed with a cancellation message because background_jobs.status does not currently support cancelled.

## 2026-05-17 04:20 Europe/Bucharest

- Added cancelled as a first-class background_jobs.status value.
- Updated JobAdminService cancel action to mark queued jobs cancelled.
- Added manual cancelled-status verification script and documentation.

## 2026-05-17 04:35 Europe/Bucharest

- Added JobAdminRepository::find.
- Added platform background job detail route GET /admin/jobs/{id}.
- Linked job IDs from the job list to job detail.
- Added manual job detail verification script and documentation.

## 2026-05-17 04:50 Europe/Bucharest

- Added background_job_attempts table.
- Added JobAttemptRepository.
- Added attempt history display to platform background job detail.
- Added attempt history count to platform job list/detail reads.
- Added manual background job attempt history verification script and documentation.

## 2026-05-17 05:05 Europe/Bucharest

- Updated JobAdminService to accept JobAttemptRepository.
- Requeue action now records admin_requeued attempt-history rows.
- Cancel action now records admin_cancelled attempt-history rows.
- Wired JobAttemptRepository into JobAdminService construction in public routes.
- Added manual job action attempt-history verification script and documentation.

## 2026-05-17 05:20 Europe/Bucharest

- Added worker_heartbeats table.
- Added WorkerHeartbeatRepository.
- Added platform GET /admin/workers screen.
- Added Workers links to platform dashboard and route map.
- Added manual worker heartbeat verification script and documentation.

## 2026-05-17 05:35 Europe/Bucharest

- Added shared worker heartbeat helper at scripts/workers/heartbeat.php.
- Patched existing worker entrypoints when present to emit heartbeat records.
- Added manual worker entrypoint heartbeat verification script and documentation.

## 2026-05-17 05:50 Europe/Bucharest

- Added effective stale detection to platform Workers screen.
- Workers older than 300 seconds display as stale.
- Added age_seconds column to worker list.
- Added manual stale worker verification script and documentation.

## 2026-05-17 13:50 Europe/Bucharest

- Added minimal browser login/logout controller for local email/password auth.
- Added GET /login, POST /login, and GET /logout routes.
- Login sets artsfolio_session cookie.
- Added browser login route smoke test and documentation.

## 2026-05-17 14:05 Europe/Bucharest

- Added /login, POST /login, and /logout routes inside the tenant route block.
- Fixed tenant-host browser login route availability for bxiie.com.

## 2026-05-17 14:15 Europe/Bucharest

- Fixed browser login route construction to pass UserRepository into PasswordAuthService instead of raw PDO.

## 2026-05-17 14:25 Europe/Bucharest

- Fixed browser login route construction to pass UserIdentityRepository into PasswordAuthService.

## 2026-05-17 14:35 Europe/Bucharest

- Fixed browser login route construction to pass PasswordHasher into PasswordAuthService.

## 2026-05-17 14:45 Europe/Bucharest

- Added local diagnostic/repair script for bxiie tenant membership for the password auth test user.

## 2026-05-17 15:00 Europe/Bucharest

- Updated browser login controller to accept optional TenantContext.
- Tenant-host POST /login now passes current tenant context into PasswordAuthService.

## 2026-05-17 15:10 Europe/Bucharest

- Added diagnostic script for bxiie browser login behavior.

## 2026-05-17 15:20 Europe/Bucharest

- Reverted tenant-context browser login change because PasswordAuthService::login does not accept a tenant argument.
- Tenant and platform browser login now both call PasswordAuthService::login without tenant context.

## 2026-05-17 22:00 Europe/Bucharest

- Added production email worker loop at scripts/workers/email_loop.sh.
- Added systemd service artsfolio-email-worker.service.
- Email worker runs scripts/workers/email_run_once.php repeatedly with a 10-second sleep.
- Service uses ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env.

## 2026-05-17 Production backup verification

- Verified artsfolio-db-backup.timer.
- Verified manual database backup service execution.
- Confirmed backup files are written to /var/backups/artsfolio.

## 2026-05-17 Production hardening

- Enabled UFW firewall.
- Enabled fail2ban for SSH protection.
- Restricted exposed ports to 22/80/443.
- Verified MariaDB remains localhost-only.
- Verified backup permissions.
- Verified production services enabled at boot.

## 2026-05-17 Production deployment automation

- Added scripts/deploy/deploy_production.sh.
- Added scripts/deploy/healthcheck.sh.
- Deploy flow now supports:
  - git pull
  - migrations
  - integrity checks
  - preflight
  - service restarts
  - production health validation

## 2026-05-17 Tenant bootstrap and login routing

- Added tenant bootstrap CLI at scripts/tenant/bootstrap_tenant.php.
- Tenant bootstrap creates/updates tenants, domains, admin user/membership, starter sections when supported, and welcome email outbox rows when supported.
- Added lifecycle email template scaffolding under template/email/lifecycle.
- Documented tenant login convention: tenant admins sign in at https://tenant-domain/login, while the tenant domain root remains public portfolio content.
- Added tenant bootstrap smoke test.

## 2026-05-17 Route inventory regression coverage

- Added scripts/test/route_inventory.php.
- Route inventory verifies tenant login remains at /login on tenant domains.
- Route inventory verifies tenant root remains public content.
- Route inventory verifies platform job, worker, and settings routes are not mounted inside the tenant route block.
- Added route inventory documentation under docs/dev, docs/admin, and docs/user.

## 2026-05-17 Lifecycle email queue foundation

- Added scripts/email/queue_lifecycle_emails.php.
- Added lifecycle queue support for tenant admin onboarding and cancellation schedules.
- Tenant bootstrap now queues onboarding lifecycle emails through the lifecycle queue script.
- Added cancellation lifecycle email template scaffolding.
- Added lifecycle email documentation under docs/dev, docs/admin, and docs/user.
- Added lifecycle email queue smoke test.

## 2026-05-17 Platform signup foundation

- Added TenantSignupService for platform tenant creation.
- Added platform SignupController.
- Platform GET /signup shows tenant signup form.
- Platform POST /signup creates tenant, admin user, tenant membership, platform subdomain, provisioning jobs, and lifecycle email rows where supported by current schema.
- Successful signup redirects to https://<slug>.artsfol.io/login.
- Added platform signup smoke test and docs under docs/dev, docs/admin, and docs/user.

## 2026-05-17 Platform signup redirect behavior

- Platform signup redirects are now environment-aware.
- Production redirects to https://<slug>.artsfol.io/login.
- Local development can set APP_ENV=local and ARTSFOLIO_LOCAL_DEV_PORT=8080 to redirect to http://<slug>.artsfol.io:8080/login.
- Added signup redirect regression test and documentation under docs/dev, docs/admin, and docs/user.

## 2026-05-17 Tenant onboarding completion flow

- Added tenant admin getting-started page at /admin/getting-started.
- Platform signup now attempts to create a browser session for the new admin.
- Successful platform signup redirects to /admin/getting-started on the new tenant domain.
- Added docs for tenant getting-started flow under docs/dev, docs/admin, and docs/user.
- Added tenant getting-started smoke test.

## 2026-05-17 App-backed Caddy on-demand TLS ask endpoint

- Added GET /caddy/ask.
- Caddy ask endpoint approves platform domains and active tenant_domains.hostname rows.
- Unknown domains return 403 and should not receive on-demand TLS certificates.
- Documented that wildcard TLS for *.artsfol.io requires DNS-01 and is not used with the current plain Caddy challenge setup.
- Temporary permissive ask services on port 8088 should not be used in production.

## 2026-05-18 Artwork upload and legacy Bxiie migration scaffold

- Added tenant artwork upload staging service.
- Added tenant admin artwork upload route at /admin/artwork/upload.
- Added legacy bxiie.com inventory script.
- Added legacy image staging script.
- Added docs for artwork upload and legacy Bxiie migration.
- Added artwork upload pipeline smoke test.

## 2026-05-18 Database-backed artwork uploads

- Added migration 0010_artwork_sales_fields.sql for artworks.sale_status and artworks.price.
- Artwork upload now writes media_assets and artworks rows.
- Upload metadata maps date/year to artworks.year_created, notes to artworks.description, medium to artworks.medium, sale status to artworks.sale_status, and price to artworks.price.
- New uploaded artworks are created as draft.
- Added artwork DB upload pipeline smoke test.

## 2026-05-22 Production-safe test guard

- Added scripts/test/TestEnvironment.php.
- Mutating scripts/test files now skip when ARTSFOLIO_ENV_FILE points at production.
- Added scripts/test/production_mutation_guard.php.
- Updated preflight to verify mutating tests include the production guard.
- Documented that production preflight must not mutate live tenant data.

## 2026-05-25 admin route and analytics repair

- Tenant login GET `/login` now renders the branded auth page with the tenant artist/site name and posts to `/login` with a CSRF token.
- Platform login still accepts `/login/password`; `/login` POST is also accepted for compatibility with branded forms and older templates.
- Platform `/me` is now a branded account-purpose page. It explains that tenant administration lives on the tenant domain under `/admin`, while platform administration lives on `artsfol.io/admin` for platform staff.
- Platform admin routes now include `/admin/stats` and `/admin/contact-messages` so platform staff do not hit router 404s on the platform host.
- Tenant audit log rendering was repaired; `/admin/audit-log` now builds and displays the table body instead of returning an empty page.
- Tenant public page handlers now write `analytics_events` rows for public page, portfolio, contact, about, and artwork/image views. The tenant and platform stats pages read from `analytics_events`, so this aligns tracking with reporting.

## 2026-05-30 header, copyright, and developer-reference patch

- Public platform page headers now hide the Sign in link when `artsfolio_current_user` is present and show an Admin link for signed-in users.
- Tenant public page headers show an Admin link for signed-in users and do not add a Sign in link.
- Platform marketing footer copyright expands the `{year}` token from `platform_footer_copyright_html`. Legacy platform page footers now render a current-year ArtsFolio copyright line.
- The login-method buttons were removed from the platform home page hero; signup/login-specific pages still expose authentication choices.
- The developer reference at `/help/developer` and `/developer` now includes route descriptions and curl examples.

## Contact notification fallback and test email recipient update - 2026-06-15

- Tenant contact-message and email-signup notifications now resolve recipients through `tenant_settings.site_admin_email`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io` so public submissions are not accepted without queuing an admin notification.
- Queue-producing email smoke scripts now enqueue their test messages to `info@artsfol.io` instead of `.example.test` recipients. Fake account identity tests may still use `.example.test` users when they do not queue outbound mail.
- Added `scripts/test/contact_email_notification_static.php` and wired it into preflight to protect notification fallback behavior and real smoke-recipient routing.


## 2026-05-25 current complaints repair

- Platform admin layout now uses ArtsFolio `logo_2.png` branding instead of hardcoded Bxiie branding.
- Platform admin sidebar routes include stats, contact messages, pricing, settings, email outbox, jobs, workers, audit log, and routes.
- `/admin/settings` is a compatibility redirect to `/admin/platform-settings`.
- Platform `/login` accepts POST in addition to `/login/password` to match the home-page sign-in form path.
- Tenant `/login` renders a branded AuthPage using tenant `artist_name` or `site_title`.
- Tenant events admin supports search, status filtering, sorting, manual order editing, and archived events.
- New tenant creation seeds `tenant_settings.custom_css` from platform CSS files.
- The apply script seeds existing Bxiie tenant `custom_css` when blank.
- OAuth Google/Facebook redirect routes are mounted. Callback routes fail closed with HTTP 501 until provider credentials and tested token exchange are configured.

## 2026-05-30 header, copyright, and developer-reference patch

- Public platform page headers now hide the Sign in link when `artsfolio_current_user` is present and show an Admin link for signed-in users.
- Tenant public page headers show an Admin link for signed-in users and do not add a Sign in link.
- Platform marketing footer copyright expands the `{year}` token from `platform_footer_copyright_html`. Legacy platform page footers now render a current-year ArtsFolio copyright line.
- The login-method buttons were removed from the platform home page hero; signup/login-specific pages still expose authentication choices.
- The developer reference at `/help/developer` and `/developer` now includes route descriptions and curl examples.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## admin-session-help-stats-audit update
- Platform admin pages use the platform admin shell with `/assets/logo_2.png` branding and platform-specific navigation.
- Help is routed through linkable `/help/{topic}` pages so each help bullet can be opened as a walk-through.
- Login forms include `keep_me_logged_in`; unchecked cookies are browser-session cookies, checked cookies persist for the configured persistent-login period.
- Platform admin settings include persistent-login days and pricing fields.
- Platform stats are exposed at `/admin/stats`; tenant stats remain at tenant `/admin/stats`.
- Tenant audit-log rendering was repaired so scoped audit rows are visible instead of an empty panel.

## 2026-05-30 header, copyright, and developer-reference patch

- Public platform page headers now hide the Sign in link when `artsfolio_current_user` is present and show an Admin link for signed-in users.
- Tenant public page headers show an Admin link for signed-in users and do not add a Sign in link.
- Platform marketing footer copyright expands the `{year}` token from `platform_footer_copyright_html`. Legacy platform page footers now render a current-year ArtsFolio copyright line.
- The login-method buttons were removed from the platform home page hero; signup/login-specific pages still expose authentication choices.
- The developer reference at `/help/developer` and `/developer` now includes route descriptions and curl examples.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## Help platform polish bundle

- `/help` is now the combined Help and Developer section with an admin-style sidebar article layout.
- `/help/developer` and `/developer` require a logged-in browser session and describe core routes/endpoints for implementers.
- Platform admin layout uses `public/assets/logo_2.png` and ArtsFolio platform branding instead of tenant/Bxiie branding.
- Platform settings can manage support email, expected DNS IPv4, persistent login days, global artist-directory enablement, and platform custom CSS served from `/assets/platform-custom.css`.
- `/pricing` is a branded plan-comparison page.

## 2026-05-30 header, copyright, and developer-reference patch

- Public platform page headers now hide the Sign in link when `artsfolio_current_user` is present and show an Admin link for signed-in users.
- Tenant public page headers show an Admin link for signed-in users and do not add a Sign in link.
- Platform marketing footer copyright expands the `{year}` token from `platform_footer_copyright_html`. Legacy platform page footers now render a current-year ArtsFolio copyright line.
- The login-method buttons were removed from the platform home page hero; signup/login-specific pages still expose authentication choices.
- The developer reference at `/help/developer` and `/developer` now includes route descriptions and curl examples.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## Route/UI hotfix

- Help and Developer documentation are combined under `/help`; `/help/developer` requires login.
- Platform pricing is served by a dedicated branded pricing controller with plan comparison content.
- Platform directory is served by a dedicated directory controller with a useful empty state instead of a launch-placeholder message.
- Tenant and platform routers expose Help routes, and tenant domains accept `POST /logout`.
- Platform admin dashboard uses explanatory cards similar to the tenant admin dashboard.
- Admin and help logo sizing was reduced to avoid oversized header branding.


## 2026-05-25 help/directory route hotfix

- Restored `/help` and `/help/{article}` routing on both platform and tenant hosts through `App\Http\Controllers\Platform\HelpController`.
- Added both `topic()` and `article()` methods to the help controller to tolerate older route wiring.
- Added canonical tenant directory opt-in routes at `/admin/directory` with `/admin/platform-discovery` retained as a compatibility redirect/action alias.
- Tenant admin navigation now labels the tenant discovery control as `Directory`.
- Tenant directory opt-in values remain `platform_directory_opt_in` and `platform_directory_summary` in `tenant_settings`.


## 2026-05-25 tenant directory audit signature hotfix

- Fixed tenant directory opt-in audit writes to call `AuditLogRepository::record()` with positional arguments.
- The previous controller draft passed an array as the first argument, causing `POST /admin/directory` to fail before redirecting.
- Directory opt-in changes now write tenant-scoped `tenant.directory_settings.updated` entries for `/admin/audit-log`.


## 2026-05-25 directory read/write contract hotfix

- Fixed the public ArtsFolio directory query to match the actual schema: `tenants.name` and `tenant_domains.hostname`.
- The previous public directory query referenced obsolete/nonexistent columns, swallowed the SQL exception, and showed the false empty-state message after tenant opt-in.
- Added `scripts/debug/check_directory_contract.php` to print tenant directory opt-in state, summary, and primary hostname from the same tables used by the public directory.
- Tenant directory settings remain stored in `tenant_settings` as `platform_directory_opt_in` and `platform_directory_summary`.


## Directory final hotfix

- Public `/directory` now reads the same tenant setting keys written by Tenant Admin → Directory: `platform_directory_opt_in` and `platform_directory_summary`.
- Directory rendering supports both the current MariaDB schema (`tenants.name`, `tenant_domains.hostname`, `tenant_settings`) and the older SQLite development schema (`tenants.display_name`, `tenant_domains.domain`, `settings`).
- `scripts/debug/check_directory_contract.php` reports the platform directory switch, detected schema contract, tenant opt-in values, domains, and directory eligibility.

## Directory thumbnail selection

Tenant admins choose the public directory thumbnail from Admin → Directory. The selected published artwork ID is stored in `tenant_settings.platform_directory_thumbnail_artwork_id`; the public platform directory resolves that artwork through `artworks.primary_media_id` and `media_assets.uuid` and renders the thumbnail via the tenant site's `/media?uuid=...` endpoint. Diagnostic: `php scripts/debug/check_directory_thumbnail_contract.php`.

## Directory SQL schema fix
- Public directory tenant queries use MariaDB migration column names: `tenants.name`, `tenant_domains.hostname`, and `artworks.primary_media_id`.
- Directory card thumbnails are selected by tenant admins through `tenant_settings.platform_directory_thumbnail_artwork_id` and rendered only when the selected artwork is published and has a non-private primary media asset.
- Marketing-page directory cards and image mosaic use the same opt-in setting contract as `/directory`.
- Diagnostic script: `php scripts/debug/check_directory_thumbnail_contract.php`.

## 2026-05-26 directory thumbnail and admin separation hotfix

- Tenant directory thumbnail selection is managed at `/admin/directory` in tenant admin.
- Directory thumbnails are selected from published tenant artworks with non-private primary media.
- The selected artwork id is stored in `tenant_settings.platform_directory_thumbnail_artwork_id`.
- Platform admin layout now has distinct platform branding/background and platform-only navigation to avoid confusion with tenant admin.
- Tenant admin directory controls remain tenant-scoped; platform admins only control global directory availability.

## 2026-05-30 header, copyright, and developer-reference patch

- Public platform page headers now hide the Sign in link when `artsfolio_current_user` is present and show an Admin link for signed-in users.
- Tenant public page headers show an Admin link for signed-in users and do not add a Sign in link.
- Platform marketing footer copyright expands the `{year}` token from `platform_footer_copyright_html`. Legacy platform page footers now render a current-year ArtsFolio copyright line.
- The login-method buttons were removed from the platform home page hero; signup/login-specific pages still expose authentication choices.
- The developer reference at `/help/developer` and `/developer` now includes route descriptions and curl examples.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## 2026-05-26 admin shell refactor

- Platform admin is canonical under `/platform/admin/*`; platform `/admin/*` routes are compatibility redirects only on platform hosts.
- Tenant admin remains canonical under `/admin/*` on tenant domains.
- `App\Http\View\AdminLayout` is platform-only and has a safety fallback that delegates tenant-host `/admin/*` requests to `TenantAdminLayout` so older tenant controllers cannot leak platform navigation.
- `App\Http\View\TenantAdminLayout` contains tenant-only navigation and no platform operations links.
- Tenant directory settings live at `/admin/directory` and include opt-in, summary, and a directory thumbnail artwork selector backed by `tenant_settings.platform_directory_thumbnail_artwork_id`.
- Platform admin visual treatment uses the dark/gold control-plane shell in `public/assets/admin-shell-refactor.css`.



## Analytics location tracking update

- Tenant public analytics now writes coarse `country`, `region`, and `city` values to `analytics_events` when available.
- Location is resolved from trusted proxy headers first, then the `analytics_ip_locations` cache, then a short public-IP lookup for public addresses.
- Raw IP addresses are not stored; cached location rows are keyed by the existing anonymized analytics IP hash.
- Diagnostic: `php scripts/debug/check_analytics_location_contract.php`.

## Tenant background image setting

- Tenant admins can select a public-site background image from `/admin/settings`.
- The selected media UUID is stored in `tenant_settings.setting_key = background_media_uuid`; no schema migration is required.
- The picker lists only non-private media attached to published artwork so `/media?uuid=...` can serve the selected image through the existing public media safety gate.
- Public rendering uses CSS variables consumed by `public/assets/site.css`: `--site-bg-image`, `--site-bg-repeat`, `--site-bg-size`, and `--site-bg-opacity`.

## 2026-06-08 Generic tenant session bridge
- Tenant admin session bridging is tenant-domain generic, not bxiie-specific. Any active host owned by the same tenant can issue a short-lived one-time ticket for another host owned by that tenant. This supports `{tenant}.artsfol.io` and any active custom domain without cross-tenant cookie sharing.


## 2026-06-08 Tenant session bridge hostname hotfix
- Tenant session bridge now reads `tenant_domains.hostname` and aliases it as `domain` internally. The production schema does not contain `tenant_domains.domain`; using that column breaks `/auth/tenant-session/bridge` with SQLSTATE 42S22.
- Active bridge hosts are limited to tenant-owned platform/custom domains with `active` or `dns_verified` status.


## 2026-06-08 Tenant session bridge domain-column repair
- Repaired the tenant session bridge so all tenant-domain lookups use `tenant_domains.hostname`; production does not have `tenant_domains.domain`.
- Added `scripts/test/tenant_session_bridge_hostname_static.php` to prevent the bridge from reintroducing the missing `domain` column.

## 2026-05-29 admin user/config/email repair

- Platform admin shell logout now includes a CSRF token generated from `CsrfTokenService`.
- Platform and tenant admin shells show the logged-in user email/display name and current role context.
- Added platform user management at `/platform/admin/users` with password rotation for platform-scoped users.
- Added platform tenant drill-in at `/platform/admin/tenants/{id}` with tenant user details and tenant-user password rotation.
- Added tenant user management at `/admin/users` with tenant-user password rotation.
- Platform settings now include SMTP and Stripe/ecommerce keys in `platform_settings`.
- Tenant settings now include `site_admin_email`; contact and signup notification queueing uses this value.
- Portfolio section empty-state link in tenant artwork edit now points to `/admin/portfolio-sections`.


## 2026-05-29 admin user-management hotfix
- Added missing admin user-management classes required by `/platform/admin/users`, `/platform/admin/tenants/{id}`, and `/admin/users`.
- Deduplicated displayed platform and tenant role labels so legacy duplicate role rows do not flood admin headers.
- Password-rotation audit events use the audit_log column order correctly: tenant_id, user_id, action, entity_type, entity_id, details, ip_address.

## 2026-05-29 Caddy domain action hotfix

- Fixed platform custom-domain admin forms so DNS verification posts to `/platform/admin/domains/action`.
- Added compatibility handling for stale `POST /admin/domains/action` submissions.
- Removed normal admin exposure of Render vhost because the deployment uses Caddy on-demand TLS rather than Apache per-domain vhost artifacts.
- Changed DNS verification success behavior to mark tenant domains `active`, allowing tenant resolution and `/caddy/ask` authorization.
- Documented that `custom_domain.render_vhost` is deprecated under the Caddy deployment model.

## 2026-05-30 platform UI/admin polish
- Platform footer copyright is now stored in `platform_settings.platform_footer_copyright_html` and reused by platform public/admin chrome.
- Platform settings now include directory thumbnail size, Google/Facebook OAuth config fields, and shared reCAPTCHA keys.
- Tenant settings now include artwork display ordering and optional tenant reCAPTCHA override keys.
- Public contact/signup submissions verify reCAPTCHA when a tenant or platform secret is configured.
- Platform stats now include day/hour bar graphs and location drilldown with unique IP hashes and access counts.
- Admin color fields are progressively enhanced with color pickers and live swatches via `/assets/admin-color-fields.js`.

## 2026-05-30 header, copyright, and developer-reference patch

- Public platform page headers now hide the Sign in link when `artsfolio_current_user` is present and show an Admin link for signed-in users.
- Tenant public page headers show an Admin link for signed-in users and do not add a Sign in link.
- Platform marketing footer copyright expands the `{year}` token from `platform_footer_copyright_html`. Legacy platform page footers now render a current-year ArtsFolio copyright line.
- The login-method buttons were removed from the platform home page hero; signup/login-specific pages still expose authentication choices.
- The developer reference at `/help/developer` and `/developer` now includes route descriptions and curl examples.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## 2026-05-30 access, lifecycle, developer reference, and jobs patch

- Browser session cookies now use `App\Http\Support\SessionCookie` so `artsfol.io` and `*.artsfol.io` can share a session cookie via `.artsfol.io`; unrelated custom domains remain host-scoped by browser security rules.
- Added `users.status` lifecycle state with `active`, `suspended`, and `deleted`; suspended/deleted users cannot resolve active sessions and their existing sessions are revoked on status change.
- Platform admins can change platform user status and tenant status from platform admin screens. Tenant deletion is implemented as soft-delete/archive by setting tenant status to `archived`.
- Suspended or archived tenant domains return an ArtsFolio-branded unavailable page with HTTP 503.
- Developer route reference is expanded in `docs/dev/http-routes.md` and `/developer` falls back to the global current user when routed through legacy route wiring.
- Background job execution requires `artsfolio-background-worker.service`; the unit file lives at `scripts/systemd/artsfolio-background-worker.service` and wraps `scripts/workers/run_once.php` through `scripts/workers/run_forever.php`.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## 2026-05-30 stats/auth/API/admin lifecycle patch

- Browser session cookies are centralized through `App\Http\Support\SessionCookie` and default to `.artsfol.io` on the platform host and ArtsFolio subdomains.
- Direct cookie sharing between custom tenant domains and `artsfol.io` is not possible in browsers; custom-domain-to-subdomain seamless auth requires OAuth/OIDC or a signed one-time handoff flow.
- Platform stats now render day/hour bar graphs and a Unique IP detail dialog. New platform analytics rows store `analytics_events.ip_address`; older rows may only show IP hash prefixes.
- Platform admin tenant list includes an external link to the tenant ArtsFolio subdomain.
- Platform admins can suspend/delete users and tenants with confirmation prompts. Tenant deletion is soft deletion by status. Suspended tenants display an ArtsFolio-branded unavailable page.
- Initial OAuth2-protected administrative API routes live under `/api/admin/*` and cover tenant creation, tenant settings, and tenant-admin-visible content collections.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.



## 2026-05-30 tenant login cookie loop fix
- Fixed tenant-domain browser login/logout in `app/Http/Controllers/Auth/LoginController.php` so the `artsfolio_session` `Set-Cookie` header is returned with successful login and expired on logout.
- Added `scripts/test/browser_login_sets_session_cookie.php` to prevent a regression where login succeeds but `/admin/*` redirects back to `/login?notice=admin-login-required` because the browser never received the session cookie.


## 2026-05-30 browser session persistence fix
- Fixed `app/Platform/Auth/Session/SessionRepository.php` so session expiry is computed in PHP and inserted as `:expires_at` instead of relying on a bound placeholder inside MariaDB `INTERVAL` syntax.
- Confirmed tenant login/logout and platform signup return `Set-Cookie` with redirect responses so browser sessions can survive redirects.
- Added `scripts/test/session_repository_expiry_sql.php` as a regression check for the session persistence SQL and browser-auth Set-Cookie behavior.

## 2026-05-30 auth/session schema drift fix
- Fixed `app/Platform/Auth/Session/SessionRepository.php` so active browser session lookup no longer requires a `users.status` column.
- The current deployed/local core user schema can exist without `users.status`; session validity now depends on the session row being unrevoked and unexpired.
- Session creation stores `expires_at` as a bound timestamp computed in PHP instead of relying on a MariaDB `INTERVAL :ttl_seconds SECOND` placeholder.
- Added `scripts/test/session_repository_no_user_status.php` as a regression check for this specific schema-drift failure.

## 2026-06-08 Generic tenant session bridge
- Tenant admin session bridging is tenant-domain generic, not bxiie-specific. Any active host owned by the same tenant can issue a short-lived one-time ticket for another host owned by that tenant. This supports `{tenant}.artsfol.io` and any active custom domain without cross-tenant cookie sharing.


## 2026-06-08 Tenant session bridge hostname hotfix
- Tenant session bridge now reads `tenant_domains.hostname` and aliases it as `domain` internally. The production schema does not contain `tenant_domains.domain`; using that column breaks `/auth/tenant-session/bridge` with SQLSTATE 42S22.
- Active bridge hosts are limited to tenant-owned platform/custom domains with `active` or `dns_verified` status.


## 2026-06-08 Tenant session bridge domain-column repair
- Repaired the tenant session bridge so all tenant-domain lookups use `tenant_domains.hostname`; production does not have `tenant_domains.domain`.
- Added `scripts/test/tenant_session_bridge_hostname_static.php` to prevent the bridge from reintroducing the missing `domain` column.

## 2026-05-30 auth cookie multi-header fix
- Browser login/logout now supports multiple `Set-Cookie` headers through `App\Http\Response`.
- `App\Http\Support\SessionCookie` now clears stale host-only and `.artsfol.io` cookie variants before setting the canonical browser session cookie.
- Tenant login now returns the session cookie on the redirect response and passes tenant context to the session creation path.
- Platform password login/logout and platform signup now use the same multi-header cookie helper.
- Added `scripts/test/auth_cookie_headers.php` to prevent regressions where browser auth succeeds server-side but stale duplicate cookies keep admin pages inaccessible.

## 2026-06-08 Generic tenant session bridge
- Tenant admin session bridging is tenant-domain generic, not bxiie-specific. Any active host owned by the same tenant can issue a short-lived one-time ticket for another host owned by that tenant. This supports `{tenant}.artsfol.io` and any active custom domain without cross-tenant cookie sharing.


## 2026-06-08 Tenant session bridge hostname hotfix
- Tenant session bridge now reads `tenant_domains.hostname` and aliases it as `domain` internally. The production schema does not contain `tenant_domains.domain`; using that column breaks `/auth/tenant-session/bridge` with SQLSTATE 42S22.
- Active bridge hosts are limited to tenant-owned platform/custom domains with `active` or `dns_verified` status.


## 2026-06-08 Tenant session bridge domain-column repair
- Repaired the tenant session bridge so all tenant-domain lookups use `tenant_domains.hostname`; production does not have `tenant_domains.domain`.
- Added `scripts/test/tenant_session_bridge_hostname_static.php` to prevent the bridge from reintroducing the missing `domain` column.

## Email SMTP custom headers
- SMTP sender supports static custom message headers configured through environment variables.
- `SMTP_X_PM_MESSAGE_STREAM` sets `X-PM-Message-Stream` for Postmark SMTP message streams.
- `SMTP_EXTRA_HEADERS` accepts semicolon- or newline-separated `Name: value` entries, such as `X-PM-Tag: lifecycle; X-PM-Metadata-tenant: bxiie`.
- Header names and values are validated to prevent CRLF/header injection before SMTP DATA is sent.
- Secret SMTP credentials remain in environment/secrets files and must not be recorded in `PROJECT_STATE.md`.

## 2026-06-08 Generic tenant session bridge
- Tenant admin session bridging is tenant-domain generic, not bxiie-specific. Any active host owned by the same tenant can issue a short-lived one-time ticket for another host owned by that tenant. This supports `{tenant}.artsfol.io` and any active custom domain without cross-tenant cookie sharing.


## 2026-06-08 Tenant session bridge hostname hotfix
- Tenant session bridge now reads `tenant_domains.hostname` and aliases it as `domain` internally. The production schema does not contain `tenant_domains.domain`; using that column breaks `/auth/tenant-session/bridge` with SQLSTATE 42S22.
- Active bridge hosts are limited to tenant-owned platform/custom domains with `active` or `dns_verified` status.


## 2026-06-08 Tenant session bridge domain-column repair
- Repaired the tenant session bridge so all tenant-domain lookups use `tenant_domains.hostname`; production does not have `tenant_domains.domain`.
- Added `scripts/test/tenant_session_bridge_hostname_static.php` to prevent the bridge from reintroducing the missing `domain` column.


## 2026-05-31 - Tenant login and platform SMTP message-stream setting
- Tenant POST `/login` now carries the resolved tenant context into `PasswordAuthService::login()` so tenant-scoped browser sessions store `tenant_id`.
- Login/logout responses now attach `Set-Cookie` headers instead of calling the cookie helper and discarding the result.
- Browser session cookie handling supports repeated `Set-Cookie` values so stale host-only and `.artsfol.io` cookies can be cleared before issuing the active session cookie.
- `SessionRepository` no longer depends on a nonexistent `users.status` column and computes `expires_at` in PHP before binding it as a normal SQL value.
- Platform Admin > Platform Settings > Email delivery now stores `smtp_x_pm_message_stream`; SMTP sends it as `X-PM-Message-Stream` for Postmark message streams.
- `scripts/workers/email_run_once.php` builds its sender from `platform_settings` rather than environment-only mail configuration.

## Tenant login and admin invites
- Tenant browser login stores the tenant ID on the session and returns `Set-Cookie` on the redirect to `/admin`.
- `SessionCookie` keeps backward-compatible `issueSetCookie()` and `expireSetCookie()` aliases to avoid rolling-deploy method-name failures.
- Platform tenant detail pages show an external link to open the tenant site in a new tab when a tenant domain is available.
- Tenant admins can invite additional tenant admins from `/admin/users`; invites upsert membership/role records and queue `email_outbox` email with template key `tenant_admin_invite`.
- Regression check: `php scripts/test/tenant_login_and_invite_static.php`.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## Background job handler compatibility - 2026-05-31

- Production background worker is expected to run as `artsfolio-background-worker.service`.
- `scripts/workers/run_once.php` supports canonical domain job type `custom_domain.verify_dns` and compatibility alias `tenant.domain.verify` for older queued signup rows.
- `tenant.site.bootstrap` is handled by `App\Platform\Jobs\Handlers\TenantSiteBootstrapJobHandler` to finalize tenant provisioning idempotently.
- New signup provisioning queues `custom_domain.verify_dns` for DNS work and keeps `tenant.site.bootstrap` for tenant finalization.

## 2026-05-31 worker health, DNS results, tenant subdomain fallback

- Platform admin pages now display a background worker error banner when no worker heartbeat has been seen within 75 seconds.
- Worker heartbeat writes now use `UTC_TIMESTAMP()` and PHP reads heartbeat timestamps as UTC to prevent false stale worker status caused by timezone drift.
- Custom domain DNS verification now stores the last DNS check on `tenant_domains.dns_last_checked_at`, `tenant_domains.dns_last_result`, and `tenant_domains.dns_last_error`; Platform Admin → Domains displays these values.
- `*.artsfol.io` tenant subdomains now fall back to resolving by tenant slug when an active `tenant_domains` row is missing or stale. Platform/root/admin hostnames remain excluded from tenant fallback.
- The platform contact page now renders reCAPTCHA when `platform_settings.recaptcha_site_key` is configured and verifies submitted tokens when `platform_settings.recaptcha_secret_key` is configured.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## 2026-05-31: Production preflight email send guard
- `scripts/test/preflight.sh` no longer runs `scripts/workers/email_run_once.php` by default.
- Production deploy preflight validates email queue/template behavior but does not attempt to relay SMTP to test recipients.
- To exercise the send path in a safe local environment, run preflight with `ARTSFOLIO_PREFLIGHT_SEND_EMAIL=1` and point SMTP settings at a local sink such as MailHog.

## Production deploy final status reporting - 2026-05-31

- `scripts/deploy/deploy_production.sh` now uses an `EXIT` trap to print a final `DEPLOY SUCCEEDED` or `DEPLOY FAILED` banner on every run.
- Failure banners include the failed deploy stage, exit code, branch, and commit so preflight failures are no longer confused with completed deploys.
- The deploy stage order remains unchanged: git update, env verification, PHP lint, migrations, migration integrity, preflight, service restarts, then health check.

## 2026-06-08 Generic tenant session bridge
- Tenant admin session bridging is tenant-domain generic, not bxiie-specific. Any active host owned by the same tenant can issue a short-lived one-time ticket for another host owned by that tenant. This supports `{tenant}.artsfol.io` and any active custom domain without cross-tenant cookie sharing.


## 2026-06-08 Tenant session bridge hostname hotfix
- Tenant session bridge now reads `tenant_domains.hostname` and aliases it as `domain` internally. The production schema does not contain `tenant_domains.domain`; using that column breaks `/auth/tenant-session/bridge` with SQLSTATE 42S22.
- Active bridge hosts are limited to tenant-owned platform/custom domains with `active` or `dns_verified` status.


## 2026-06-08 Tenant session bridge domain-column repair
- Repaired the tenant session bridge so all tenant-domain lookups use `tenant_domains.hostname`; production does not have `tenant_domains.domain`.
- Added `scripts/test/tenant_session_bridge_hostname_static.php` to prevent the bridge from reintroducing the missing `domain` column.

## Production deploy worker requirement

`artsfolio-background-worker.service` is deploy-critical. `scripts/deploy/deploy_production.sh` must fail if the unit is missing or inactive. The deploy script checks the unit with `systemctl cat artsfolio-background-worker.service` and verifies activity with `systemctl is-active --quiet artsfolio-background-worker.service`.

## Hardened production deploy worker health - 2026-05-31

- `scripts/deploy/deploy_production.sh` now fails if `artsfolio-background-worker.service` is missing or inactive.
- `scripts/deploy/healthcheck.sh` now treats the background worker as a required service instead of warning and passing.
- SIGINT/SIGTERM during deploy now exit with code 130 and produce a `DEPLOY FAILED` banner rather than a false success banner.
- Required production services are `php8.4-fpm`, `caddy`, `mariadb`, `artsfolio-email-worker.service`, and `artsfolio-background-worker.service`.

## 2026-06-08 Generic tenant session bridge
- Tenant admin session bridging is tenant-domain generic, not bxiie-specific. Any active host owned by the same tenant can issue a short-lived one-time ticket for another host owned by that tenant. This supports `{tenant}.artsfol.io` and any active custom domain without cross-tenant cookie sharing.


## 2026-06-08 Tenant session bridge hostname hotfix
- Tenant session bridge now reads `tenant_domains.hostname` and aliases it as `domain` internally. The production schema does not contain `tenant_domains.domain`; using that column breaks `/auth/tenant-session/bridge` with SQLSTATE 42S22.
- Active bridge hosts are limited to tenant-owned platform/custom domains with `active` or `dns_verified` status.


## 2026-06-08 Tenant session bridge domain-column repair
- Repaired the tenant session bridge so all tenant-domain lookups use `tenant_domains.hostname`; production does not have `tenant_domains.domain`.
- Added `scripts/test/tenant_session_bridge_hostname_static.php` to prevent the bridge from reintroducing the missing `domain` column.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## Contact, signup, reCAPTCHA, and navigation UX
- Tenant contact and email signup submissions redirect back to branded tenant pages with inline success/error notices instead of standalone error pages.
- Tenant public reCAPTCHA is tenant-specific only; platform keys are not reused on tenant/custom domains to avoid Google invalid-domain errors.
- Tenant contact pages include an email-list signup form and public tenant pages show a one-minute visitor signup modal.
- Public tenant and platform navigation styling reserves stable tab width to reduce page-to-page tab movement.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## Tenant first-party CAPTCHA controller integration
- Tenant public pages use `FirstPartyCaptcha::render()` directly and must not call the removed `HomeController::recaptchaWidget()` helper.
- Tenant contact/signup submissions verify first-party CAPTCHA server-side and render branded same-page errors for visitors.

## Platform admin routing
- Platform-admin routes are canonical to `artsfol.io`; tenant-host `/platform/admin...` requests redirect to `https://artsfol.io/platform/admin...` so tenant routing cannot swallow platform admin URLs.

## Platform admin redirect compatibility
- Tenant-host platform-admin redirects use `Response::html(..., 302, Location)` because `App\Http\Response` has no `redirect()` factory method.


## Tenant first-party CAPTCHA form-fix
- Tenant first-party CAPTCHA is progressively enhanced: the checkbox is usable without JavaScript after server dwell time, and JavaScript only temporarily disables it.
- The honeypot field is hidden inline and in CSS to prevent tenant CSS/cache drift from exposing it to visitors.
- Tenant form JavaScript now surfaces server error text instead of replacing failures with a generic message.

## Tenant admin navigation
- Tenant public Admin links point to `/admin` on the current tenant host. Platform admin remains canonical at `https://artsfol.io/platform/admin`; tenant public navigation must not link to `/platform/admin`.

## Tenant admin navigation
- Tenant public Admin links point to `/admin` on the current tenant host. Platform admin remains canonical at `https://artsfol.io/platform/admin`; tenant public navigation must not link to `/platform/admin`.

## Tenant public admin navigation

- Tenant public pages use `/admin` for their Admin nav link on the tenant host.
- Platform admin remains canonical at `https://artsfol.io/platform/admin` and must not be emitted in tenant public navigation.


## Tenant chrome guardrails
- Tenant public pages must use `/admin` for tenant admin links and must not call `PlatformChrome::platformAdminLink()` directly.
- Tenant form JavaScript is loaded from `/assets/tenant-forms.js`; controllers must not inline the asset contents into public pages.
- `scripts/test/tenant_chrome_static.php` enforces these rules during preflight.


## Email outbox diagnostics
- Platform Admin → Email Outbox displays `email_outbox.last_error` for failed rows.
- `EmailSenderFactory::fromEnvironment()` honors both `EMAIL_DRIVER` and legacy `MAIL_TRANSPORT`; `MAIL_TRANSPORT=log` maps to dry-run behavior.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## Custom-domain deploy safety

- Added migration `0020_preserve_bxiie_custom_domains.sql` to preserve and repair the Bxiie tenant mappings for `bxiie.artsfol.io`, `bxiie.com`, and `www.bxiie.com`.
- Production preflight/deploy must treat tenant custom-domain rows as durable data and must not delete or disable them as test cleanup.
- If a custom hostname is assigned to the wrong tenant, migration code must not silently steal it; deployment should fail and require explicit operator repair.

## 2026-05-31 engagement and tenant-user admin update

- Contact messages and email-list signups now store IP address, user agent, city, region, and country when location can be resolved.
- Tenant admin contact-message and email-signup screens and CSV exports show IP and location details.
- Public tenant contact/signup forms and the platform `/contact` page use the first-party ArtsFolio CAPTCHA widget instead of silently depending on absent Google reCAPTCHA tenant secrets.
- Tenant owner user-management actions now include promotion of tenant admins to tenant owner and deletion of invited or active tenant users from that tenant. Delete actions revoke tenant-scoped access and active sessions and write `tenant.user.deleted` audit rows.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## 2026-05-31 tenant branding and site image patch
- Tenant public home body headline now uses tenant setting `artist_name`; menu brand and browser title continue to use `site_title`/page title behavior.
- Tenant artwork supports seeded artwork types `portfolio_images` and `site_images` through `artwork_types` and `artwork_type_assignments`.
- Public portfolio/home/artwork queries require `portfolio_images`; published `site_images` are available to About, Contact, and Background pickers and are not public portfolio items unless also marked `portfolio_images`.
- About, Contact, and Background image pickers use thumbnails and selectable opacity.
- Tenant admin user deletion requires typing `delete` in confirmation and continues to audit tenant user deletion.
- Tenant admin upload page now uses the tenant admin shell instead of an unbranded bare HTML page.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## Email branding
- All queued outbound email is branded centrally through `App\Platform\Email\BrandedEmail` from `EmailOutboxRepository::queue()`.
- Individual email templates should keep message-specific content only and avoid duplicating the shared ArtsFolio header/footer.


## 2026-06-02 tenant visual/admin auth repair

- Tenant public pages and tenant admin pages now share page background, top bar, menu panel, heading spread, content surface, and text color CSS variables.
- Tenant settings include `menu_background_enabled` so the visible navigation background wash can be suppressed without hiding navigation links.
- Tenant contact and signup forms use tenant surface styling and the tenant form asset version was bumped to avoid stale browser JavaScript.
- `App\Http\Support\SessionCookie` includes plural `issueHeaders()` and `expireHeaders()` aliases used by platform login/logout controllers.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## 2026-06-02 pricing, commission, and platform stats patch
- Platform Admin → Plans & Billing now edits plan price, description, active status, display order, custom-domain inclusion, allowed artwork count, and allowed email-address count.
- Platform sales commission is stored in `platform_settings.platform_sales_commission_basis_points` and displayed on public pricing and tenant billing surfaces.
- Free tenant pages show an ArtsFolio notification/link; public pricing explicitly notes this.
- Platform stats location drill-down now opens IP detail scoped to the selected location rather than the global IP list.
- Platform tenant status/lifecycle audit calls use the correct `AuditLogRepository::record()` argument order.

## 2026-06-02 platform pricing, tenant lifecycle, and invite controls
- Platform tenant deletion is soft-delete only, requires a typed `delete` confirmation, writes audit, and removes deleted tenants from the default platform tenant list.
- Platform pricing is editable from `/platform/admin/pricing`; platform admins can create plans, update prices, set artwork/email limits, custom-domain inclusion, active state, display order, and platform sales commission disclosure.
- Strawman plans are seeded for Free, Studio, Professional, and Collective; the Free plan includes an ArtsFolio notification/link disclosure.
- Platform and tenant user admin screens support invite send/resend through the local email outbox.
- Tenant menu visual settings now suppress the nav panel background and background image when menu opacity is zero or the menu panel is disabled.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## 2026-06-02 sales readiness phase 1
- Published, unsold `for_sale` artworks display price on portfolio cards and artwork detail pages.
- Artwork detail pages include a contact link with the artwork title prefilled for inquiry.
- Artwork rows now include `is_one_off` and `inventory_quantity` fields for original versus inventory-backed items.
- Tenant settings include `sales_notes`; public artwork detail pages display the notes with sales context.
- Tenant pages show first-party cookie consent in preparation for sessions, analytics, signup/contact forms, and tenant-scoped cart support.
- Stripe checkout, cart/order records, sales workflow, recent sales, commission calculation, and sales analytics remain phase-two/phase-three work.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## 2026-06-02 sales phase 2
- Added tenant-scoped shopping carts, order records, order items, Stripe Checkout session creation, and Stripe webhook completion handling.
- Stripe model: Stripe Checkout with destination charges when tenant setting `stripe_connected_account_id` is configured; platform settings hold Stripe keys and webhook secret.
- Paid plans only: public add-to-cart and checkout are gated by active paid plan assignments.
- Tenant admin now has a Sales workflow page for ordered, acknowledged, packed, and shipped status plus shipping details.
- Platform admin now has a Sales overview with tenant visibility and commission totals.
- Public platform home can show recent sales with tenant and artwork links.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## 2026-06-02 pricing, billing, auth, and social stabilization
- Tenant custom domains and tenant subdomains share the same tenant route set; tenant `/signup` redirects to the tenant signup/contact surface instead of 404.
- Tenant `/password/forgot` accepts POST, creates password reset tokens, and queues branded reset email through `email_outbox`.
- Platform pricing setup now includes the same billing features shown in tenant usage: artwork records, media storage GB, email subscribers, contact messages, custom domains, admin users, and online checkout/allow-sales.
- Tenant owners can upgrade or downgrade from `/admin/billing`; the change updates `tenant_plan_assignments` and legacy `tenant_settings.billing_plan`.
- Platform tenant detail includes a complementary billing override. Complementary tenants do not pay platform service billing but still pay platform commission on sales.
- Tenant footer renders configured Instagram, Facebook, and LinkedIn URLs.
- Cart contact email is collected before checkout, and `scripts/email/queue_abandoned_cart_emails.php` queues 12-hour and 24-hour abandoned-cart reminders without sending SMTP directly.
- Production smoke/predeploy tests must not send real emails; delivery remains worker-owned and should only be triggered manually against a safe SMTP sink.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## Billing economics update - 2026-06-02
- Tenant signup now intentionally supports both GET /signup and POST /signup; route preflight expects both.
- Plan pricing includes plan-scoped credit card percentage and fixed fee settings.
- Sales orders persist platform commission, estimated credit card fees, and estimated seller net revenue.
- Stripe Checkout application fees include platform commission plus estimated card fees so tenants receive sale amount minus commission and card charges.
- Complementary tenants waive subscription billing only; platform commission and credit card charges still apply to sales.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## 2026-06-02 dashboard signal update
- Platform Admin dashboard now shows operating and business signals: tenant counts, paid-capable tenants, complimentary tenants, 30-day GMV, platform commission, seller net, contact queue, background job attention, recent sales, recent tenants, and active plan economics.
- Tenant Admin dashboard now shows site and sales action signals: published/draft artworks, for-sale inventory, low-stock multiples, 30-day views, subscribers, contact queue, open orders, current plan, sales readiness, and next-action guidance.
- Dashboard queries are defensive around optional sales/engagement tables so rolling deployments and local environments do not turn the admin landing page into a brick.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## 2026-06-02 dashboard schema alignment
- Platform and tenant dashboards use production table names, including `plans`, `sales_orders`, and `background_jobs`.
- Dashboard optional table checks use MariaDB `SHOW TABLES` / `SHOW COLUMNS` instead of fragile assumptions.
- Dashboard sections now report concise admin-visible query failures instead of misleading missing-table empty states.

## 2026-06-08 admin dashboard, sales analytics, login, and release checklist fixes
- Tenant dashboard sales inventory counts now use `artworks.sale_status = 'for_sale'` instead of the nonexistent `artworks.is_for_sale` column.
- Tenant dashboard subscriber counts now use `email_signups.consent_status` from the production schema.
- Platform dashboard tenant counts are null-safe and continue to exclude deleted tenants.
- Tenant and platform sales analytics routes are wired to the existing SalesAnalyticsController classes.
- Tenant login failure now returns the branded login page with a retry invitation instead of an unbranded raw `Invalid login` page.
- Tenant public Admin tab is hidden for anonymous visitors and shown only when a browser session user is present.
- Printable application checklists and comprehensive test-stack guidance were added under `docs/admin`, `docs/dev`, and `docs/user`.

## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.


## Recent operational fixes

- 2026-06-08 dashboard counts hotfix: Platform and tenant admin dashboard controllers must use `Database::connect(dirname(__DIR__, 5))`; using level 6 points outside the project and makes metrics/table checks falsely report zero or missing tables.

## 2026-06-08 Contact and signup location schema repair
- Added `database/migrations/0031_contact_engagement_location_schema_repair.sql` to repair schema drift where contact/signup repositories write `country`, `region`, and `city`, but older databases may not have those columns.
- Migration is additive and idempotent; it does not delete or rewrite production contact or signup data.

## 2026-06-08 Generic tenant session bridge
- Tenant admin session bridging is tenant-domain generic, not bxiie-specific. Any active host owned by the same tenant can issue a short-lived one-time ticket for another host owned by that tenant. This supports `{tenant}.artsfol.io` and any active custom domain without cross-tenant cookie sharing.


## 2026-06-08 Tenant session bridge hostname hotfix
- Tenant session bridge now reads `tenant_domains.hostname` and aliases it as `domain` internally. The production schema does not contain `tenant_domains.domain`; using that column breaks `/auth/tenant-session/bridge` with SQLSTATE 42S22.
- Active bridge hosts are limited to tenant-owned platform/custom domains with `active` or `dns_verified` status.


## 2026-06-08 Tenant session bridge domain-column repair
- Repaired the tenant session bridge so all tenant-domain lookups use `tenant_domains.hostname`; production does not have `tenant_domains.domain`.
- Added `scripts/test/tenant_session_bridge_hostname_static.php` to prevent the bridge from reintroducing the missing `domain` column.

## 2026-06-08 Email list management and custom-domain admin sessions

- Added tenant email signup source/notes management, CSV import, deletion, search, sort, and filtered export.
- Added one-time tenant session bridge tickets so an admin logged in on a tenant `artsfol.io` subdomain can enter the same tenant admin on a verified custom domain without a second password prompt.
- Admin tab on tenant public pages is shown only when a browser user is signed in.
- Cross-domain auth uses short-lived one-time tickets because browsers cannot share ordinary cookies between unrelated domains such as `bxiie.artsfol.io` and `bxiie.com`.

## 2026-06-08 Generic tenant session bridge
- Tenant admin session bridging is tenant-domain generic, not bxiie-specific. Any active host owned by the same tenant can issue a short-lived one-time ticket for another host owned by that tenant. This supports `{tenant}.artsfol.io` and any active custom domain without cross-tenant cookie sharing.


## 2026-06-08 Tenant session bridge hostname hotfix
- Tenant session bridge now reads `tenant_domains.hostname` and aliases it as `domain` internally. The production schema does not contain `tenant_domains.domain`; using that column breaks `/auth/tenant-session/bridge` with SQLSTATE 42S22.
- Active bridge hosts are limited to tenant-owned platform/custom domains with `active` or `dns_verified` status.


## 2026-06-08 Tenant session bridge domain-column repair
- Repaired the tenant session bridge so all tenant-domain lookups use `tenant_domains.hostname`; production does not have `tenant_domains.domain`.
- Added `scripts/test/tenant_session_bridge_hostname_static.php` to prevent the bridge from reintroducing the missing `domain` column.

## 2026-06-08 Email list management and custom-domain admin sessions

- Added tenant email signup source/notes management, CSV import, deletion, search, sort, and filtered export.
- Added one-time tenant session bridge tickets so an admin logged in on a tenant `artsfol.io` subdomain can enter the same tenant admin on a verified custom domain without a second password prompt.
- Admin tab on tenant public pages is shown only when a browser user is signed in.
- Cross-domain auth uses short-lived one-time tickets because browsers cannot share ordinary cookies between unrelated domains such as `bxiie.artsfol.io` and `bxiie.com`.

## 2026-06-08 Generic tenant session bridge
- Tenant admin session bridging is tenant-domain generic, not bxiie-specific. Any active host owned by the same tenant can issue a short-lived one-time ticket for another host owned by that tenant. This supports `{tenant}.artsfol.io` and any active custom domain without cross-tenant cookie sharing.


## 2026-06-08 Tenant session bridge hostname hotfix
- Tenant session bridge now reads `tenant_domains.hostname` and aliases it as `domain` internally. The production schema does not contain `tenant_domains.domain`; using that column breaks `/auth/tenant-session/bridge` with SQLSTATE 42S22.
- Active bridge hosts are limited to tenant-owned platform/custom domains with `active` or `dns_verified` status.


## 2026-06-08 Tenant session bridge domain-column repair
- Repaired the tenant session bridge so all tenant-domain lookups use `tenant_domains.hostname`; production does not have `tenant_domains.domain`.
- Added `scripts/test/tenant_session_bridge_hostname_static.php` to prevent the bridge from reintroducing the missing `domain` column.

## 2026-06-08 Platform admin invite repository repair

- Added `AdminUserRepository::assignPlatformRole()` so `/platform/admin/users/invite` can create/reuse a user and assign the platform admin role without a fatal method error.
- Platform role assignment checks for an existing `tenant_id IS NULL` row before inserting because MySQL unique indexes do not de-duplicate NULL tenant IDs reliably for this purpose.
- Added `scripts/test/platform_user_invite_repository_static.php` and wired it into preflight.


## 2026-06-10

- Added tenant admin artwork placement matrix at `/admin/artworks/placement` for assigning artwork to the home page and portfolio sections with thumbnail-first rows.
- Added portfolio/home artwork ordering workflow at `/admin/portfolio-sections/order`; ordering writes to `homepage_artwork_assignments.sort_order` and `artwork_section_assignments.sort_order`.
- Tenant password reset requests are now scoped to users attached to the current tenant through `tenant_memberships` or legacy `tenant_users`; unrelated users and mailing-list-only addresses do not receive tenant reset links.

## 2026-06-10 signup, logout, and home artwork visibility
- Signup passcodes are verified before the platform signup flow displays site detail fields.
- Tenant logout revokes the active server-side session in addition to expiring session cookies.
- Tenant artwork admin links to the artwork placement matrix and section artwork order UI.
- Tenant home pages render only artworks explicitly assigned to the home page when homepage_artwork_assignments exists.
- 2026-06-10: Patched tenant logout to revoke server-side session tokens, mounted platform `/password/forgot`, refreshed artwork publish/unpublish AJAX state, and added default `$` display for bare numeric public artwork prices.
## Platform contact outbox repair - 2026-06-15

- The public platform contact form at `https://artsfol.io/contact` now queues `email_outbox` rows with `template_key=platform.contact_notification` instead of only displaying a success notice.
- Platform contact recipients resolve through `ARTSFOLIO_PLATFORM_CONTACT_EMAIL`, then `ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL`, then `info@artsfol.io`; `.example.test` is not used for queued contact mail.
- Added `scripts/test/platform_contact_email_static.php` and wired it into preflight to protect platform contact queueing.

- Tenant auth security: tenant logout revokes the server-side session row through `SessionRepository`, and tenant password reset token issue/redeem paths require active tenant membership.
- Auth/domain security: tenant logout uses `SessionRepository`, tenant password reset requires active tenant membership, domain plan usage ignores default `artsfol.io` subdomains and `www` aliases, and tenant deletion removes tenant domain rows for reuse.
- Domain DNS/reset hardening: DNS verification uses `ARTSFOLIO_SERVER_PUBLIC_IP` instead of literal `SERVER_PUBLIC_IP`; tenant reset fails closed on missing tenant id; platform custom-domain add accepts tenant id or slug.
- Login/jobs UI: tenant login pages do not show create-account links; background jobs admin shows execution timestamps.
- Platform access/login/jobs UI: developer resources are login-gated, platform marketing nav includes Pricing, tenant login hides create-account links, and background jobs show execution timestamps.
- Platform UI/access: developer resources require login, Pricing appears in platform navigation, tenant login suppresses create-account link, and jobs admin shows execution timestamps.
- Pricing UI: public pricing cards show admin user limits per tier and force high-contrast price display on tinted plan cards.
- Pricing/plans UI: pricing shows admin-user limits, dark Studio card text is readable, and edit plan fields are widened for fee/card/currency values.

## Cloudflare Turnstile and social auth guide update - 2026-06-15

- Public platform and tenant contact/signup form protection now uses Cloudflare Turnstile settings: `turnstile_site_key` and `turnstile_secret_key`. Tenant keys override platform keys; blank tenant keys inherit platform keys; blank secrets preserve local/staging form behavior.
- The legacy `App\Services\FirstPartyCaptcha` class name remains as the compatibility facade, but it now renders `cf-turnstile` and verifies `cf-turnstile-response` through Cloudflare Siteverify.
- Platform and tenant settings screens now label spam protection as Cloudflare Turnstile. Existing reCAPTCHA setting keys are only read as temporary migration fallback for site key display.
- Added `docs/admin/turnstile-and-social-auth.md` and `docs/dev/oauth-provider-guide.md` with implementation guidance for Turnstile, Google OAuth, and Facebook Login.
- Social auth redirect routes already exist, but provider callbacks still fail closed until token exchange, state validation, identity linking, and normal ArtsFolio session creation are implemented and tested.

## 2026-06-15 Google and Facebook browser OAuth implementation

- Implemented Google and Facebook browser OAuth login callbacks in `App\Http\Controllers\Auth\OAuthController` with one-time session-bound state validation, server-side code exchange, profile lookup, identity linking, normal `user_sessions` creation, and `artsfolio_session` cookie issuance.
- OAuth provider settings are managed in Platform Admin → Platform Settings and persisted in `platform_settings` using `oauth_auth_base_url`, `google_oauth_client_id`, `google_oauth_client_secret`, `facebook_oauth_client_id`, and `facebook_oauth_client_secret`; browser OAuth no longer reads provider credentials or callback base URL from `/etc/artsfolio/artsfolio.env`.
- Added `scripts/test/oauth_browser_login_static.php` plus admin/dev/user docs for social login setup and operation.

## Lifecycle email safety

- Lifecycle emails such as `lifecycle.welcome` must be associated with both `tenant_id` and `user_id` before being queued.
- Smoke/preflight email tests must not commit deliverable lifecycle outbox rows by default.
- `scripts/test/email_outbox.php` uses `welcome-test@example.test` and rolls back unless explicitly run with `ARTSFOLIO_EMAIL_OUTBOX_TEST_COMMIT=1`.


## Free plan access signup codes

- Platform signup codes support a `free_months` type for granting temporary free access to any active plan.
- `tenant_signup_codes.free_access_months` stores the number of free months granted by the code.
- `tenant_plan_assignments.complimentary_until` stores the end of the free access period.
- `tenant_plan_assignments.granted_by_signup_code_id` links the plan assignment back to the redeemed signup code.
- `tenant_plan_assignments.billing_note` records the operational reason for the complimentary/trial assignment.
- Free access signup codes waive ArtsFolio platform plan billing for the configured period only; they do not waive card fees, sales commissions, shipping, or taxes.

## Signup code revocation and existing-tenant free access

- Platform admins can revoke any tenant signup code type from Platform Admin -> Signup Codes.
- Revoked codes are blocked by the same active-code validation used for public signup and tenant billing redemption.
- Tenant owners can apply `free_months` codes from Tenant Admin -> Billing to an existing tenant.
- Existing-tenant free access updates `tenant_plan_assignments` to the selected active plan with `status = trial`, `complimentary_until`, `granted_by_signup_code_id`, and `billing_note`.
- Existing-tenant code redemption increments `tenant_signup_codes.redemption_count` and can move the code to `redeemed` when its redemption limit is reached.

## Signup code used status and list filters

- Signup codes use `used` as the terminal status when redemption count reaches the configured limit.
- Migration `0036_signup_code_used_status.sql` converts legacy `redeemed` rows to `used`.
- Platform Admin → Signup Codes hides used and revoked codes by default.
- The signup-code page has persistent browser-scoped options to show used codes and/or revoked codes.
- Revoked and used codes remain invalid for signup and tenant billing because validation requires `status = active`.


## CAPTCHA split

- Platform ArtsFolio forms on `artsfol.io` continue to use Cloudflare Turnstile from platform settings.
- Tenant public forms use `App\Services\FirstPartyCaptcha` instead of Turnstile.
- Tenant CAPTCHA is session-backed, single-use, short-lived, includes a honeypot field, requires a human confirmation checkbox, and enforces minimum dwell time.
- Tenant custom domains and subdomains do not require Cloudflare Turnstile hostname registration.
- Tenant settings must not expose Turnstile site-key or secret-key fields.

## Email signup duplicate notification guard

- Public tenant email-list submissions check for an existing active subscriber before queueing `tenant.signup_notification`.
- Active email-list status means `pending` or `confirmed`. Repeat submissions for active addresses update metadata but do not queue another tenant-admin notification email.
- Repeat public submissions preserve `pending` and `confirmed` consent status so a confirmed address is not downgraded to pending.
- Previously unsubscribed addresses are not active; a later signup can restore them to pending and send a notification.

## Platform legal pages

- Public platform legal pages are available at `/terms` and `/privacy` on `artsfol.io`.
- Platform footers should link to Terms and Privacy.
- Terms cover tenant content rights, public/discovery image usage, directory opt-in, sales, fees, shipping, signup codes, acceptable use, suspension, liability, and contact.
- Privacy policy covers collected data, OAuth login data, sales data, contact/email-list data, cookies/CAPTCHA/security, retention, sharing, and data deletion instructions for direct, Facebook, and Google login data.
- Legal copy is operationally comprehensive but should be reviewed by counsel before broad commercial launch.

## Canonical OAuth and logout behavior

- Google and Facebook OAuth callbacks are canonical on `https://artsfol.io`.
- Tenant login pages link to `https://artsfol.io/auth/google` and `https://artsfol.io/auth/facebook` with a trusted tenant admin `return_to` URL.
- Tenant-local `/auth/google` and `/auth/facebook` routes redirect to the platform OAuth entrypoint instead of trying to run provider OAuth on tenant hosts.
- OAuth `return_to` accepts relative URLs, `artsfol.io` hosts, `*.artsfol.io` hosts, and active tenant custom domains from `tenant_domains`.
- Platform `/logout` supports GET for direct navigation and POST with CSRF for form logout.

- Platform signup code list now shows invite email delivery state by reading matching `platform.tenant_signup_invite` outbox rows: not sent, queued/not sent yet, partially sent, or sent with counts and latest timestamps.
## OAuth-backed site signup email locking

- Google/Facebook OAuth-backed tenant signup now uses the provider email from `$_SESSION['artsfolio_oauth_profile']` as the site-owner email.
- The `/signup` details form renders that email as a readonly field and explains that it cannot be changed during OAuth signup.
- The signup POST handler uses the session provider email instead of trusting the browser-submitted `email` field.
- Signup-page OAuth links include a trusted `return_to` path that preserves the signup passcode query string.
- Regression coverage lives in `scripts/test/oauth_signup_email_lock_static.php` and is called from `scripts/test/preflight.sh`.


- 2026-06-20: CSRF failures now render through the branded error page system via `Response::invalidCsrf()`. Controllers must not return raw `Invalid CSRF token` HTML; platform logout and tenant/admin POST security failures should show platform or tenant chrome. Static preflight coverage checks this behavior.


## 2026-06-20 - Platform user lifecycle UI repair

- Fixed `/platform/admin/users` so the user list reads `users.status` instead of hardcoding every platform user as active.
- Soft-deleted platform users are hidden from the default platform user list. Suspended users remain visible with a suspended status and a reactivate action.
- Platform user suspend/delete helpers now route through `setUserStatus()` so sessions are revoked consistently.
- Platform user lifecycle validation failures now render through branded error pages instead of raw `<h1>` responses.
- Added `scripts/test/platform_user_lifecycle_static.php` to preflight.


## 2026-06-20 14:55 America/New_York

- Tenant `/admin/getting-started` now includes ArtsFolio platform branding because the page is part of the platform signup/OAuth tenant-creation handoff.
- Tenant getting-started text remains source-controlled in `app/Http/Controllers/Tenant/Admin/GettingStartedController.php`; no database-backed editor currently exists for this page.
- Added `scripts/test/tenant_getting_started_branding_static.php` and wired it into preflight to prevent removal of the platform logo/handoff branding.

- Tenant public navigation/sidebar readability: new tenant sites default menu/sidebar text to dark `--text-color` / `#1f1a14` on light/tan menu panels. The tenant-admin application sidebar remains white text on a dark background. Static coverage lives in `scripts/test/tenant_sidebar_readability_static.php`.

- Tenant color palette buttons on `/admin/settings` use delegated JavaScript click handling in `public/assets/admin-color-fields.js` so nested swatch/name clicks apply presets reliably. Tenant opacity inputs use `step="0.01"` so palette values and manual values such as `0.72` pass browser validation.

- Tenant Admin Settings repeats the `Save site settings` submit button below each major settings section. All buttons submit the full `/admin/settings` form; they are convenience controls, not section-scoped partial saves. Regression coverage: `scripts/test/tenant_settings_section_save_static.php`.

- Tenant color palette buttons use versioned tenant admin CSS and high-specificity palette styles so generic admin button rules cannot force all palette buttons to black.
- Tenant color palettes now include ten presets; Midnight Olive and Ultraviolet Paper were added after the original eight. Static checks require every palette to define topbar/menu text colors with at least 4.5:1 contrast against their backgrounds.
- Tenant Admin Settings is split into subpages at `/admin/settings?section=identity|typography|colors-backgrounds|miscellaneous|custom-css`. Each subpage posts the active section and `SettingsController::settingsKeysForSection()` saves only the fields visible on that subpage so hidden settings are not cleared. Static coverage: `scripts/test/tenant_settings_subpages_static.php`.
- Tenant Admin Directory settings now live under `/admin/settings?section=directory`; `/admin/directory` redirects there for backward compatibility. The Directory subpage owns `platform_directory_opt_in`, `platform_directory_thumbnail_artwork_id`, and `platform_directory_summary`, and static coverage lives in `scripts/test/tenant_directory_settings_subpage_static.php`.
- Tenant directory thumbnail selection moved from the Directory settings dropdown to a Directory thumbnail checkbox on Tenant Admin -> Artworks; Directory settings now shows the selected preview and links back to Artworks.
- Tenant typography controls now include an expanded local/system font list and seven typography preset buttons. Presets fill font and size controls for later tweaking; they do not save until the normal site settings save action.
- Tenant typography controls now use slideable range inputs whose changed value drives the hidden submitted px value, update the same font preview instead of rendering a separate size preview, and public tenant pages emit computed typography rules at the end of the page so saved settings win on home, portfolio, about, contact, artwork, forms, and footer text.
- Tenant typography settings now apply through a late public inline typography style block emitted after `/tenant.css`, so saved fonts and sizes win on home, portfolio, about, contact, artwork, forms, and footer pages. Typography size controls use sliders plus numeric pixel fields instead of raw CSS text entry.
- Tenant typography settings use a versioned public `site.css` link and `admin-typography-fields.js` live preview helper so font picker/size previews update immediately and saved public font settings are not hidden by stale CSS.
- Tenant Admin > Settings includes a Typography section with curated local/system font pickers and public font-size controls for home, portfolio, about, contact, artwork, forms, and footer text. Public pages emit tenant typography CSS variables from `HomeController`; `site.css` consumes them; static coverage lives in `scripts/test/tenant_typography_settings_static.php`.

## 2026-06-20 - Scale readiness phase 0 and media variants phase 1

- Added `scripts/dev/seed_scale_dataset.php` for non-production scale fixtures. Scale tenants use slugs like `scale-0001`, hostnames like `scale-0001.artsfol.io`, and the tenant setting `scale_dataset_marker = artsfolio-scale-fixture-v1`.
- Scale fixture cleanup only removes tenants matching both the `scale-` slug prefix and the scale marker setting, then deletes dependent tenant rows and `storage/uploads/artwork/scale-*` directories.
- The scale fixture script refuses production-looking environments unless `--allow-production-like` is explicitly passed for disposable staging databases.
- Added `database/migrations/0038_media_asset_variants.sql` and `App\Tenant\Media\MediaVariantService` so media assets can have `original`, `thumb`, `medium`, and `large` variants.
- `ArtworkUploadService` now creates media variant rows during upload, and `MediaController` honors `/media?uuid=...&variant=thumb|medium|large|original` with long-lived cache headers and ETags.
- Added `scripts/maintenance/backfill_media_variants.php` for existing media records after migration 0038.
- Static regression coverage lives in `scripts/test/scale_fixture_static.php` and `scripts/test/media_variants_static.php`; both are wired into preflight.
## 2026-06-20 - Platform-admin scale tenant controls

- Added `/platform/admin/scale-tenants` for platform owners/admins to create, reset, and remove synthetic scale fixture tenants from the UI.
- The UI and CLI now share `App\Platform\ScaleTesting\ScaleTenantFixtureService`, so isolation rules stay consistent across browser and script use.
- Scale fixture removal remains constrained to tenants with both slug prefix `scale-` and tenant setting `scale_dataset_marker = artsfolio-scale-fixture-v1`; real tenant rows are not selected by cleanup unless both markers are present.
- Platform admin create/remove actions require typed confirmation text and write audit log events.
- Added `/admin/scale-tenants` redirect to `/platform/admin/scale-tenants`, platform admin sidebar navigation, and `scripts/test/platform_scale_tenants_static.php` preflight coverage.
- Deployment docs now point to the workstation Downloads paths for the combined Phase 0/1 plus scale-admin update script and archive.

## 2026-06-20 - PHP 8.5 media variant compatibility hotfix

- Removed deprecated `imagedestroy()` calls from `App\Tenant\Media\MediaVariantService`; PHP 8.5 reports these calls because GDImage objects are released automatically when references leave scope.
- `scripts/test/media_variants_static.php` now fails if the new media variant service reintroduces `imagedestroy()`.


## 2026-06-20 - Scale tenant admin browser-timeout hotfix

- Platform-admin scale tenant create/reset/remove actions now enqueue background jobs instead of running large fixture creation or cleanup inside the browser request.
- Added `App\Platform\Jobs\Handlers\ScaleTenantFixtureJobHandler` and registered `scale_tenants.seed` and `scale_tenants.cleanup` in `scripts/workers/run_once.php`.
- `/platform/admin/scale-tenants` now tells operators that fixture actions require the background worker and that progress is visible under `/platform/admin/jobs`.
- Scale fixture audit logging is best-effort so audit-log schema/user-id drift cannot break the admin action.
- Static coverage in `scripts/test/platform_scale_tenants_static.php` now checks that scale admin actions enqueue jobs and that the worker has handlers for them.


## 2026-06-20 - Scale tenant users and plan-mix fixture hotfix

- Scale fixture seeding now assigns each synthetic tenant a plan, rotating through `free`, `studio`, `pro`, and `collective`. The tenant setting `billing_plan` is kept in sync with the assigned plan.
- Each scale tenant now receives active tenant users. The first generated user is tenant owner and the remaining generated users are tenant admins. User counts follow `plans.allowed_admin_users` when available, with defaults of free=1, studio=3, pro=10, and collective=25.
- Generated scale users use the isolated email domain `@scale-fixtures.artsfol.io` and local test password `ScaleTenantFixture!2026`. These accounts are fixtures only and must not be treated as real user accounts.
- Scale cleanup now removes generated fixture users and their tenant memberships, tenant_users rows, role assignments, identities, sessions, OAuth tokens, reset/verification tokens, platform role rows, audit user rows, and email outbox user rows before deleting the users. Users are selected only when linked to marked scale tenants and using the fixture email domain.
- Platform-admin and CLI tenant count defaults remain 1000, but the previous 5000-tenant artificial cap has been removed. Operators can request any positive tenant count against disposable local/staging databases.
- Platform-admin scale tenant summary now shows generated user counts and plan distribution. Static coverage in `scripts/test/platform_scale_tenants_static.php` checks the plan mix, generated users, cleanup user deletion, and uncapped tenant count behavior.

## Phase 3 analytics hardening (2026-06-20)
- Public analytics use `AnalyticsRecorder` and do not perform request-time external geolocation.
- `0039_analytics_rollups.sql` adds hourly/daily rollups and analytics indexes.
- `analytics.rollup` is a recurring five-minute background job.
- Platform jobs list/detail show elapsed execution time.
- Trusted localhost HTTP smoke probes suppress only expected missing-token audit entries.



## 2026-06-22 - Bucketed analytics rollup recovery

- `AnalyticsRollupService` now rebuilds one UTC hour and one UTC day per transaction rather than aggregating an entire multi-day range in one SQL statement.
- Interrupted rollup runs are safe to rerun because each bucket is deleted and replaced independently.
- `scripts/maintenance/rebuild_analytics_rollups.php` retains the positional day argument and adds `--days`, `--from`, and `--to` options for bounded recovery runs.
- Production MariaDB should use disk-backed `/var/lib/mysql-tmp` for `tmpdir` instead of the approximately 1 GB RAM-backed `/tmp` mount. Apply this with `sudo ./scripts/ops/configure_mariadb_tmpdir.sh`. The application-level bucket change remains required even with the larger temporary directory.
- Regression coverage lives in `scripts/test/phase3_rollup_bucketing_static.php` and is included in preflight.

## 2026-06-22 - Artwork aggregation page-size controls

- Public portfolio, Tenant Admin Artworks, and Artwork Placement Matrix now accept a validated `per_page` query parameter.
- Admin page-size options are 10 through 100 in steps of 10, defaulting to 50.
- The public portfolio retains the Phase 5 default of 24 and offers 10, 20, 24, then 30 through 100.
- Section/filter/page links and post-action return URLs preserve the selected page size.
- Unsupported page sizes fall back to the route default rather than permitting unbounded queries.
- Regression coverage lives in `scripts/test/artwork_page_size_controls_static.php`.

## Phase 5 artwork catalog scaling (2026-06-22)
- Public portfolio pagination: 24 published artworks per page, with section-aware page links.
- Tenant artwork admin pagination: 50 rows per page with search, status, sale, image, section, and sort filters.
- Artwork placement matrix pagination: 50 rows per page; saves affect only visible artwork IDs and preserve other pages.
- Migration 0041 adds artwork catalog and section-order indexes.
- Worker installer now disables both legacy singleton worker services before enabling templated instances.

<!-- End of file. -->

## Phase 5 placement matrix column filters (2026-06-22)

- Added a live text filter that narrows visible placement-matrix columns by portfolio-section name without requiring horizontal scrolling.
- Made Home page and portfolio-section column headings selectable to show only artworks assigned to the chosen column on the current page.
- Added All columns and All artworks reset controls, accessible state/status text, and reapplication after AJAX paging.
- Added focused static regression coverage and preflight wiring.

## 2026-06-22 - Portfolio section action prominence

- Replaced the compact inline Portfolio Sections action links with three prominent responsive action cards.
- Added short descriptions clarifying the difference between section creation, section/home ordering, and bulk placement.
- Added focused static regression coverage and preflight wiring.

## 2026-06-22 - Directory search and sorting

- Added public directory search across projected artist display name and directory summary.
- Added allow-listed Name A–Z, Name Z–A, and Recently updated sort modes.
- Preserved search/sort state through pagination and bookmarkable URLs.
- Added progressive AJAX replacement for the directory results region with normal GET fallback and Back/Forward support.
- Added focused static regression coverage and preflight wiring.


## Phase 8: route and bootstrap separation

- Reduced `public/index.php` to a small front controller.
- Added `App\Http\AppKernel` for request context, guards, service preparation, and dispatch.
- Split tenant and platform route registration into dedicated route files.
- Added route inventory snapshot tests and duplicate-route detection.
- Moved tenant password-reset recipient validation into a dedicated class.

## 2026-06-22 Phase 8 contact-route static test hotfix

- Updated `scripts/test/platform_contact_management_static.php` to inspect `app/Http/Routes/platform.php` after the Phase 8 route extraction.
- The test no longer incorrectly requires platform contact routes to remain in `public/index.php`.
- No runtime route behavior changed.

- 2026-06-22: Updated the platform terms/privacy static regression check for the Phase 8 route split so it reads `app/Http/Routes/platform.php` instead of assuming legal routes remain in `public/index.php`.

## 2026-06-22 Phase 8 canonical OAuth/logout static test hotfix

- Updated `scripts/test/canonical_oauth_logout_static.php` to inspect `app/Http/Routes/tenant.php` after the Phase 8 route extraction.
- The test no longer incorrectly requires tenant OAuth redirect closures and GET logout registration to remain in `public/index.php`.
- No runtime authentication or routing behavior changed.

## 2026-06-22 Phase 8 directory-thumbnail static test hotfix

- Updated `scripts/test/tenant_directory_thumbnail_artworks_static.php` to inspect `app/Http/Routes/tenant.php` after the Phase 8 route extraction.
- The test no longer incorrectly requires the directory-thumbnail POST route to remain in `public/index.php`.
- No runtime artwork or directory-thumbnail behavior changed.

## 2026-06-22 Phase 8 scale-tenant static test hotfix

- Updated `scripts/test/platform_scale_tenants_static.php` to inspect `app/Http/Routes/platform.php` after the Phase 8 route extraction.
- The test no longer incorrectly requires scale-tenant controller wiring and routes to remain in `public/index.php`.
- No runtime scale-fixture or routing behavior changed.

## 2026-06-22 Phase 9 monitor reporting hotfix

- Added macOS/Linux default-route detection to prevent false local CRIT results.
- Dry-run email delivery no longer assumes an outbox ID or claims that mail was sent.
- Monitor run IDs now use a database fallback and reject zero IDs.
- Health email subjects explicitly identify CRITICAL and WARNING states.
- Email metric order is CRIT first, then WARN, OK, and INFO.

## Phase 9 operations console and restart alerts
- Added normalized monitor metrics, admin dashboard, shareable authenticated run/metric URLs, trend lines, user-friendly HTML health email, and boot-ID restart notifications.


### 2026-06-22 background-job concurrency hotfix
- Standardized background queue readiness, stale recovery, and monitor queue-age calculations on MariaDB local `CURRENT_TIMESTAMP`.
- Added per-job advisory execution locks and prevented stale recovery of actively locked jobs.
- Added atomic singleton scheduling for analytics rollups and expired inventory release jobs.
- Corrected the paid-sales monitor metric to query `payment_status`.

## 2026-06-22 admin attention, watermark, and list controls
- Tenant navigation shows new-message and new-email-signup badges.
- Public image watermarking is opt-in and rendered at response time; originals are unchanged.
- Platform/public navigation order is canonicalized through `PlatformChrome::topNavigation()`.
- Admin tables have current-page sort/filter enhancement.
- Operations trends and system checks accept UTC date ranges; checks support status filtering and paging.

<!-- End of file. -->
