## 2026-09-08 Instagram / social publishing

- Added provider-neutral social publishing with Instagram as the first provider.
- Migration `0070_social_instagram_publishing.sql` adds encrypted provider connections, caption templates, portfolio-section template assignments, tenant editor capability storage, social post/history records, ordered carousel media, publish-attempt diagnostics, short-lived media tokens, and artwork `social_caption` / `social_hashtags` fields.
- Instagram publishing is available only to Studio, Professional, and Collective tenants.
- Tenant owners/admins may publish automatically. Editors require explicit `social.publish` permission managed from Tenant Admin Users as **Publish to social media**.
- Instagram connection uses OAuth only. ArtsFolio never stores Instagram passwords. Access tokens are encrypted with libsodium using `ARTSFOLIO_SOCIAL_TOKEN_KEY`; OAuth state is separately signed with `ARTSFOLIO_SOCIAL_STATE_KEY`.
- Current Meta permissions are `instagram_business_basic` and `instagram_business_content_publish`; intended accounts are Instagram Creator or Business accounts.
- Social Caption is distinct from the public Description. Tenant-wide default hashtags and artwork-specific hashtags are normalized and may be rendered independently or together.
- Internal artwork Notes are deliberately excluded from the social template placeholder library.
- Multiple named caption templates are supported. Resolution is explicit Compose selection, then single-section assignment, then tenant default. Multi-section conflicts deliberately fall back to tenant default instead of inventing precedence.
- Instagram Compose supports primary-image default selection, additional artwork images, same-section candidate priority, up to 10 carousel items, explicit ordering, and non-destructive Original / Square / Portrait 4:5 / Landscape 1.91:1 crops with saved focus coordinates.
- Scheduled posts are immutable snapshots relative to later artwork edits. Pending posts can be explicitly reopened, edited, rescheduled, posted immediately, or canceled until a worker claims them for publishing.
- All scheduled times are stored in UTC and displayed/accepted in the signed-in ArtsFolio user's configured IANA time zone.
- `social.publish_due` runs through the existing background job worker. Due posts are claimed atomically, transient network/rate-limit/server failures retry with bounded backoff, authorization failures pause as `authorization_required`, and successful posts persist the remote media ID/permalink.
- Instagram publishing-limit usage is checked through Meta's `content_publishing_limit` endpoint before publication.
- Publication uses dedicated JPEG derivatives under `storage/social/instagram/...`; source artwork originals are never exposed solely for Meta fetch. Meta receives short-lived tenant-bound URLs backed by random tokens whose hashes are stored in `social_media_tokens`.
- Success and final-failure notifications are queued through the existing email outbox to tenant owners/admins plus editors who have `social.publish` permission. Ordinary tenant users receive no social status email.
- Instagram controls are surfaced on the Artworks grid, Edit Artwork page, authorized public artwork view, and Tenant Admin navigation. The redundant outer `Artworks` heading on Edit Artwork is removed by the social UI enhancement.
- History records published Instagram permalinks and preserves canceled/failed/authorization-required states. ArtsFolio does not delete an already-published Instagram post remotely.
- Environment configuration is documented in `.env.example`; production requires Meta client ID/secret, exact callback registration, `ARTSFOLIO_SOCIAL_TOKEN_KEY`, and `ARTSFOLIO_SOCIAL_STATE_KEY` before OAuth/publishing can work.
- Regression coverage lives in `scripts/test/social_instagram_publishing_static.php` and is wired into `scripts/test/preflight.sh`; migration integrity now validates migration 0070 artifacts.
- Detailed architecture/operations documentation is in `docs/dev/social-instagram-publishing.md`; tenant-admin and end-user guidance is in `docs/admin/instagram-publishing.md` and `docs/user/instagram-publishing.md`.

## Home page thumbnail watermark behavior

- Home page artwork cards request the `thumb` media derivative.
- Thumbnail derivatives remain unwatermarked.
- Medium, large, and original public artwork images continue to follow tenant watermark settings.

## Public multi-variant artwork detail and Home Page editing

- Public artwork pages define a local cents formatter used by multi-variant option labels.
- The artwork edit page includes Home Page as a special placement checkbox.
- Draft and published portfolio artwork may be assigned to Home Page.
- Site-only branding records are excluded by requiring the `portfolio_images` type.
- Existing Home Page order is retained; newly assigned artwork is appended in increments of 10.


## Home Page special artwork section

- Tenant admins manage homepage artwork selection from a non-deletable special section on Portfolio Sections.
- Home Page membership is stored in `homepage_artwork_assignments`, not `portfolio_sections`.
- Published and draft non-site artworks belonging to the current tenant may be selected; public visibility follows normal portfolio status behavior.
- Site-type branding artworks remain excluded from the Home Page artwork picker.
- Assignment replacement is tenant-scoped, CSRF-protected, role-protected, and transactional.


## 2026-07-10 Security hardening

- Tenant-scoped OAuth admin API requests now require the bearer token tenant_id to exactly match the URL tenant ID.
- Stripe webhooks fail closed when stripe_webhook_secret is absent and reject invalid signatures.
- Tenant and platform logins plus password-reset requests use the database-backed RateLimiter.
- Caddy on-demand TLS authorization uses APCu positive/negative caching when available and database-backed request throttling.
- Public watermark rendering now honors ETags before GD work and caches rendered variants under storage/cache/watermarks.
- Artwork placement and ordering mutations require CSRF validation.
- Stripe secrets are no longer rendered into platform settings HTML and blank submissions preserve configured values.
- Production session cookies force Secure; local non-production development may still use HTTP.
- Tenant slugs and domain hostnames receive strict format validation; self-service signup normalizeSlug is implemented.
- Development MariaDB binds only to 127.0.0.1:3307 and bootstrap explicitly disables displayed PHP errors.

## 2026-07-08 Go-live hardening pass
- Repaired `/platform/admin/jobs/{id}` so it accepts the router parameter array and reads `$params['id']`, avoiding a runtime TypeError on platform job detail pages.
- Added `scripts/test/platform_job_detail_route_params_static.php` and included it in `scripts/test/preflight.sh`.
- Updated preflight syntax scans to ignore macOS AppleDouble `._*` files so Finder metadata does not become part of application validation.
- Added `ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env` to `scripts/systemd/artsfolio-billing-scheduler.service` so billing scheduler runs with the same production environment convention as the other services.
- Added go-live, commerce, Stripe Connect, billing/payout, and artist launch readiness documentation.
- Moved deploy debris such as AppleDouble files, `.patch-backups`, root `.docx` exports, and known `.bak` controller copies into this run's `.update-backups` folder instead of deleting them outright.

## 2026-07-03 Artwork internal notes v5 stabilization
- Repaired artwork admin edit labels so the legacy/private `notes` field is labelled `Internal notes` and the public HTML field remains `Public notes HTML`.
- Rewrote the artwork edit notes/grid/stock static test to inspect the real `name="notes"` and `name="notes_html"` field windows without PHP string interpolation.
- This repair does not modify migrations.

## 2026-06-30 America/New_York

- Added shopping cart phase 1 schema plan in `database/migrations/0054_cart_variants_shipping_aliases.sql`.
- Phase 1 creates `artwork_sale_config`, `artwork_sale_variants`, and `sales_cart_aliases`, extends cart/order/reservation rows with variant, shipping, known-owner, and 1/3/7-day abandonment fields, and backfills default sale variants from existing artwork sale fields.
- Sales carts are tenant-scoped and domain-portable. Each hostname keeps a first-party cart cookie; future bridge endpoints will map hashed local cart tokens through `sales_cart_aliases` to one canonical `sales_carts` row for the tenant.
- Phase 1 intentionally keeps the existing artwork-level cart uniqueness and runtime behavior until later phases make add-to-cart and checkout variant-aware.



## 2026-06-23 America/New_York

- Tenant Miscellaneous settings now persist `new_artwork_default_status` (`draft` or `published`) for future artwork uploads.
- Artwork contact messages resolve and append the tenant-owned public image URL server-side.
- Tenant Artworks workflow links use the Portfolio Sections action-card treatment.
- The public platform contact form now captures a normalized topic and includes it in stored and emailed contact context.
- No schema migration or service restart is required beyond the normal application deployment/reload path.
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
- Added dev/admin/user documentation and auth template documentation for identity and membership.

## 2026-05-16 10:25 Europe/Bucharest

- Added local email/password authentication service foundation.
- Added session token generation and hashed session persistence.
- Added manual verification script for password registration, login, and active session lookup.
- Documented local password authentication for dev and admin audiences.
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
- Added development documentation for API denial flow.

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
- Added dev/admin/user contact and email signup persistence documentation.

## 2026-05-16 18:20 Europe/Bucharest

- Added tenant contact form and email signup form routes/pages.
- Added CSRF-protected POST handlers using tenant-scoped repositories/services.
- Added persistence plus notification queueing for contact messages and email signups.
- Added basic public form success/error messages.

## 2026-05-16 18:40 Europe/Bucharest

- Added SMTP custom header support for ArtsFolio tenant context.
- Email workers now send tenant/template metadata through sender interfaces.
- Added manual SMTP header formatting verification script.

## 2026-05-16 19:00 Europe/Bucharest

- Added explicit consent checkbox to public email signup forms.
- Added source_ip/user_agent persistence to email signup records.
- Added manual consent metadata verification script.

## 2026-05-16 19:20 Europe/Bucharest

- Added CSV response helper for admin exports.
- Added contact message and email signup CSV export routes for tenant admins.
- Added tenant role-protected admin exports using owner/admin membership enforcement.

## 2026-05-16 19:40 Europe/Bucharest

- Added platform admin CSV export routes for tenant listings and recent users.
- Added platform admin role middleware using platform role assignments.

## 2026-05-16 20:00 Europe/Bucharest

- Added tenant audit log admin list page.
- Added platform audit log admin list page.
- Added basic search/filter support for audit log pages.

## 2026-05-16 20:20 Europe/Bucharest

- Added platform settings admin page and persistence.
- Added tenant settings admin page and persistence.
- Settings mutations now create audit_log records.

## 2026-05-16 20:40 Europe/Bucharest

- Added local-dev SMTP custom header smoke test.
- Added preflight script for PHP/shell syntax, migration checks, tenant resolution, auth/session/email/role/audit/settings tests, and optional HTTP smoke tests.

## 2026-05-16 21:00 Europe/Bucharest

- Added tenant custom-domain admin list/add flow.
- Custom-domain creation queues DNS verification through background_jobs.
- Domain automation remains dry-run for web-server changes.

## 2026-05-16 21:20 Europe/Bucharest

- Added background worker systemd unit/timer examples.
- Added worker service unit static checks.

## 2026-05-16 21:40 Europe/Bucharest

- Added tenant admin list pages for artworks, portfolio sections, contact messages, email signups, and domains.
- Added contact message status updates and email signup pagination/export links.

## 2026-05-16 22:00 Europe/Bucharest

- Added platform admin list pages for tenants, users, background jobs, and domain artifacts.
- Added platform settings and platform audit log navigation links.

## 2026-05-16 22:20 Europe/Bucharest

- Added rate limiting middleware/service and persistence table.
- Added login, password reset, signup, and Caddy ask rate-limit hooks.

## 2026-05-16 22:40 Europe/Bucharest

- Added production mutation guard foundation for infrastructure-sensitive admin actions.
- Added platform/tenant admin audit records around guarded operations.

## 2026-05-16 23:00 Europe/Bucharest

- Added first-party CAPTCHA progressive enhancement and anti-bot timing/honeypot checks for public tenant forms.

## 2026-05-16 23:20 Europe/Bucharest

- Added tenant custom CAPTCHA settings and rendering integration.

## 2026-05-16 23:40 Europe/Bucharest

- Added duplicate email-signup handling and notification behavior.

## 2026-05-17 00:00 Europe/Bucharest

- Added email-signup spam probability scoring and admin display/filter support.

## 2026-05-17 00:20 Europe/Bucharest

- Added AJAX email signup delete support for tenant admins.

## 2026-05-17 00:40 Europe/Bucharest

- Added worker DNS tenant scoping checks and scaling-phase worker tests.

## 2026-05-17 01:00 Europe/Bucharest

- Added tenant background job handlers for domain/bootstrap operations.

## 2026-05-17 01:20 Europe/Bucharest

- Added tenant admin list/search/pagination refinements.

## 2026-05-17 01:40 Europe/Bucharest

- Added public HTTP contact/signup route smoke tests.

## 2026-05-17 02:00 Europe/Bucharest

- Added route inventory generation for debugging/admin review.

## 2026-05-17 02:20 Europe/Bucharest

- Added platform help/sidebar contrast checks and SMTP reserved-recipient guards.

## 2026-05-17 02:40 Europe/Bucharest

- Added platform operations run-detail and background-job route regression checks.

## 2026-05-17 03:00 Europe/Bucharest

- Added authentication cookie-header and tenant-login-context regressions.

## 2026-05-17 03:20 Europe/Bucharest

- Added platform SMTP message-stream setting regression checks.

## 2026-05-17 03:40 Europe/Bucharest

- Added tenant login/invite static checks and tenant chrome regression checks.

## 2026-05-17 04:00 Europe/Bucharest

- Added email outbox diagnostics and platform email-template admin checks.

## 2026-05-17 04:20 Europe/Bucharest

- Added branded error-page and tenant-admin canonical-layout checks.

## 2026-05-17 04:40 Europe/Bucharest

- Added tenant color palette, typography, sidebar readability, and branded-CSRF regression coverage.

## 2026-05-17 05:00 Europe/Bucharest

- Added email HTML logo pipeline, password-reset route email, and admin-auth content checks.

## 2026-05-17 05:20 Europe/Bucharest

- Added signup/logout home visibility, auth-artwork follow-up, and tenant-auth/domain-security checks.

## 2026-05-17 05:40 Europe/Bucharest

- Added domain DNS/reset, login-jobs UI, platform access/login-jobs, pricing UI, and pricing contrast checks.

## 2026-05-17 06:00 Europe/Bucharest

- Added signup-code revocation/billing, used-filter, invite status/free-months, and invitation-template variant checks.

## 2026-05-17 06:20 Europe/Bucharest

- Added signup complimentary checkout, post-registration email, and auto-invite regression checks.

## 2026-05-17 06:40 Europe/Bucharest

- Added terms/privacy, canonical OAuth logout, OAuth signup email lock, tenant-settings subpage, directory, contact, scale, media-variant, and settings-snapshot checks.

## 2026-05-17 07:00 Europe/Bucharest

- Added phase 5 artwork catalog, artwork page-size controls, phase 3 analytics/rollup, MariaDB tmpdir, and platform-job execution-time checks.

## 2026-05-17 07:20 Europe/Bucharest

- Added artwork AJAX pagination/filters, portfolio section actions, custom public page slugs, tenant artwork-contact defaults, and sidebar-upload checks.

## 2026-05-17 07:40 Europe/Bucharest

- Added directory projection/search, sales inventory, monitoring, job concurrency/deadlock/detail, analytics freshness, migration discipline, and monitoring service checks.

## 2026-05-17 08:00 Europe/Bucharest

- Added phase 8 routing, attention/notification watermark navigation, content/background picker layout, about/contact layout, OAuth button branding, and user-timezone preference checks.

## 2026-05-17 08:20 Europe/Bucharest

- Added watermark runtime/exclusion regressions, artwork logo/list tools, email outbox containment/UTC, OAuth lifecycle nav, platform user status/auth, platform operations timezone, artwork edit notes/grid/stock, analytics bot filter, and shipping-contact checks.

## 2026-05-17 08:40 Europe/Bucharest

- Added backup operations/restic/settings/reliability/control regressions, email logo tokens/Stripe description/operations copy, backup nav cleanup, plan commission/Stripe fee, backup timezone, onboarding tab, default tenant contrast, and public visibility/signup prompt checks.

## 2026-05-17 09:00 Europe/Bucharest

- Added operations/trial details, custom CSS seed, platform operations route constructor, signup-code trial duration, tenant password-forgot guard, tenant timezone layout, template/login/unknown-domain, Caddy subdomain ask, tenant billing complimentary status, default CSS cleanup, contributor workflow, upload section assignment, unpublished preview media, checkbox clarity, and image watermark checks.

## 2026-05-17 09:20 Europe/Bucharest

- Added artwork grid section/preview shortcuts, watermark render/preview/lookup/transparent-trim/scale/stacking checks, artwork type filter, event month/year picker, homepage special section, public variant detail/homepage edit, homepage unwatermarked thumbnails, help-sidebar final-layer, platform polish, email outbox selected timezone, and reboot-required-reason checks.

<!-- End of file. -->