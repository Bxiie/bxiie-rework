# Instagram and social publishing

ArtsFolio contains a provider-neutral social publishing subsystem with Instagram as the first provider. The implementation is intentionally separated from artwork originals, tenant authentication, and Meta credentials so a failed provider integration cannot mutate source artwork or leak credentials.

## Architecture

Migration `database/migrations/0070_social_instagram_publishing.sql` adds:

- `artworks.social_caption` and `artworks.social_hashtags`
- `social_connections`
- `social_templates`
- `social_section_templates`
- `tenant_user_permissions`
- `social_posts`
- `social_post_items`
- `social_publish_attempts`
- `social_media_tokens`

Migration `database/migrations/0071_social_instagram_hardening.sql` adds durable provider-account binding. It changes social connection uniqueness from one row per tenant/provider to one row per tenant/provider/external account, adds `social_connections.is_default`, and adds `social_posts.social_connection_id`. This lets the initial UI expose one default Instagram account while preserving the data model for multiple accounts later.

The social provider layer lives under `app/Tenant/Social/`. `SocialRepository` owns tenant-scoped persistence, `SocialPermissionService` owns the `social.publish` capability, `SocialTemplateRenderer` renders caption placeholders, `SocialImageService` creates publication-only JPEG derivatives, `InstagramClient` encapsulates authenticated publishing calls, `TenantInstagramOAuthClient` performs OAuth for one tenant-owned Meta app, and `SocialPublishingService` runs due posts from the background worker.

The browser-facing social routes are isolated in `SocialInstagramCredentialsController`, `SocialFrontController`, `SocialComposeApiController`, and `SocialPermissionAdminController`. `public/index.php` dispatches the tenant credential/OAuth controller before the legacy social route surface so `/admin/social/connect` and `/social/instagram/callback` cannot fall back to platform-wide Meta credentials.

## Meta application configuration

Instagram publishing uses Instagram API with Instagram Login. Each tenant owns and configures its own Meta application. A tenant administrator enters the Meta App ID and App Secret on **Tenant Admin → Instagram**.

The tenant App ID is stored in `tenant_settings.instagram_client_id`. The App Secret is encrypted with `SocialTokenCipher` before being stored in `tenant_settings.instagram_client_secret_ciphertext`. The plaintext App Secret is never rendered back to the browser. Leaving the secret field blank during a later save preserves the existing encrypted secret.

Platform environment configuration contains only the shared callback URI and ArtsFolio's own cryptographic keys:

```text
ARTSFOLIO_INSTAGRAM_REDIRECT_URI=https://artsfol.io/social/instagram/callback
ARTSFOLIO_SOCIAL_TOKEN_KEY=...
ARTSFOLIO_SOCIAL_STATE_KEY=...
```

Do not define platform-wide `ARTSFOLIO_INSTAGRAM_CLIENT_ID` or `ARTSFOLIO_INSTAGRAM_CLIENT_SECRET` values. OAuth must always instantiate `TenantInstagramOAuthClient` from the initiating tenant's saved credentials.

Generate the token key:

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

Generate a separate state-signing key:

```bash
openssl rand -hex 32
```

Never commit either platform secret. `ARTSFOLIO_SOCIAL_TOKEN_KEY` is a long-lived encryption key. Changing or losing it makes existing encrypted Instagram access tokens and tenant Meta App Secrets unreadable and requires tenant reconfiguration/reconnection.

Every tenant-owned Meta application registers the same production callback URI. OAuth state carries the tenant ID, tenant slug, initiating user ID, return host, expiration, and nonce under an HMAC signature. On callback ArtsFolio resolves the tenant again and verifies that the resolved tenant ID matches the signed state before decrypting that tenant's App Secret and exchanging the authorization code.

The current implementation requests `instagram_business_basic` and `instagram_business_content_publish`.

## Security model

ArtsFolio never stores an Instagram password. Tenant Meta App Secrets and Instagram access tokens are encrypted with libsodium secretbox before database persistence. Temporary publication media URLs use a random 256-bit token; only the SHA-256 token hash is stored. The media URL is tenant-host-bound and expires after two hours.

Owners/admins receive publishing permission automatically from the canonical tenant-role contract. Editors require an active tenant membership plus an explicit `tenant_user_permissions` row with `permission_key = 'social.publish'`. The admin-only `/admin/social/editor-permission` endpoint manages this capability.

Social publishing is enabled only when the tenant's current plan slug is `studio`, `pro`, or `collective`.

Internal artwork notes are not available to the template engine. Template placeholders are limited to intentionally publishable artwork/tenant metadata.

## Instagram account identity

A successful OAuth connection makes that account the default account for new posts. Reconnecting the same Instagram account refreshes that account's stored encrypted token. Connecting a different account creates or reactivates a separate `social_connections` row and makes it the new default.

Every new `social_posts` row stores the exact `social_connection_id` used when it is submitted. Editing or rescheduling a pending post does not change that binding. Therefore connecting a different Instagram account cannot silently redirect an already scheduled publication. The Compose page surfaces the bound account when editing a pending post.

Disconnecting Instagram revokes only the currently selected account and moves pending posts bound to that account to `authorization_required`. Posts bound to another stored account are not rewritten.

## Caption templates

A tenant receives a default template on first use. Additional named templates are stored in `social_templates`. A portfolio section may select a template through `social_section_templates`.

When an artwork belongs to exactly one section, that section template is eligible to become the initial Compose template. When multiple sections are present, ArtsFolio deliberately falls back to the tenant default rather than inventing precedence. The artist may select any template in Compose.

Supported placeholders currently include:

```text
{title}
{artist_name}
{artist}
{year}
{medium}
{dimensions}
{year_medium_dimensions}
{description}
{social_caption}
{social_or_description}
{artwork_url}
{website}
{site_url}
{portfolio_url}
{portfolio_name}
{section_name}
{section_names}
{price}
{availability}
{copyright_year}
{copyright_holder}
{default_hashtags}
{artwork_hashtags}
{hashtags}
```

`{social_or_description}` uses Social Caption when present and otherwise falls back to Description. Empty placeholders collapse cleanly rather than leaving repeated blank lines.

## Scheduling and snapshots

All scheduled timestamps are persisted as UTC. Compose displays and accepts times in the signed-in ArtsFolio user's configured IANA time zone through `UserTimezoneContext`.

Schedule conversion accepts a local wall-clock time only when exactly one UTC instant maps to it. Nonexistent spring-forward times and ambiguous fall-back times are rejected rather than silently normalized to a different instant.

A submitted social post is a snapshot. `social_posts.snapshot_json`, its bound social connection, rendered caption, hashtags, template ID, and ordered `social_post_items` preserve the submitted state. Later artwork edits do not mutate the scheduled post. An explicit Edit action rewrites the pending content snapshot before publishing begins while preserving the original account binding.

Pending states are `draft`, `scheduled`, `failed`, and `authorization_required`. `publishing` is claimed atomically and cannot be edited or canceled. Final successful state is `published`; user cancellation is `cancelled`.

## Background worker and publication idempotency

Migration 0070 seeds the first `social.publish_due` background job. `scripts/workers/run_once.php` handles it and always schedules its next iteration through `BackgroundJobRepository::enqueueSingleton()`.

The default dispatcher interval is 60 seconds. Each pass claims due `social_posts` using a conditional status update. A `failed` post is due only when `next_attempt_at` is non-null and has arrived. Final failures therefore do not loop forever merely because their original `scheduled_at` is in the past.

Transient provider rate-limit/server failures are retried with bounded backoff. Authentication failures transition to `authorization_required`. Final failure and successful publication both queue email notifications to tenant owners/admins and editors who have `social.publish`.

The `/media_publish` boundary is handled specially. As soon as Meta returns a published media ID, ArtsFolio stores it in `social_posts.remote_post_id` before making another provider request. ArtsFolio then confirms that exact media object with a GET request. If confirmation fails or the worker stops after Meta accepted the post, a retry sees the stored remote ID and performs confirmation only. It does not call `/media_publish` again, preventing duplicate Instagram posts caused by an uncertain response boundary.

## Image derivatives and carousel behavior

Instagram media is not served directly from the artist's original file for publication. `SocialImageService` creates a JPEG derivative under:

```text
storage/social/instagram/<tenant-id>/<post-id>/item-<item-id>.jpg
```

Crop modes are Original, Square, Portrait 4:5, and Landscape 1.91:1. Crop focus coordinates are stored in `social_post_items.crop_json`; reopening a scheduled post restores crop mode, focus, and carousel order.

The UI limits a carousel to ten selected images. Candidate artwork from the same portfolio section is sorted ahead of other tenant artwork. The source artwork starts selected for a new post. A single caption applies to the whole carousel.

Meta fetches each derivative through a short-lived URL such as:

```text
https://tenant.example/social/media/<random-token>
```

## Operations and troubleshooting

Check the worker:

```bash
sudo systemctl status 'artsfolio-background-worker@*.service' --no-pager
sudo journalctl -u 'artsfolio-background-worker@*.service' --since '30 minutes ago' --no-pager
```

Inspect social queue state without exposing tokens:

```sql
SELECT id, tenant_id, social_connection_id, status, scheduled_at, next_attempt_at,
       publish_attempts, remote_post_id, LEFT(last_error, 240) AS last_error
FROM social_posts
ORDER BY id DESC
LIMIT 50;
```

Never print or log `social_connections.token_ciphertext`, tenant `instagram_client_secret_ciphertext`, decrypted access tokens, decrypted client secrets, or the platform social token key.

If one tenant cannot begin OAuth, verify that tenant's saved Meta App ID and App Secret and confirm that the callback URI shown in Tenant Admin exactly matches the Meta app configuration. If all tenants are affected, verify `ARTSFOLIO_SOCIAL_TOKEN_KEY`, `ARTSFOLIO_SOCIAL_STATE_KEY`, the shared callback URI, and application routing before changing any tenant credentials.

If all scheduled posts stop, first verify that a `social.publish_due` background job is queued/running and that background workers are healthy. If one tenant reports `authorization_required`, reconnect the specific account bound to that post instead of globally restarting or rotating credentials.

## Deployment

Production deployment order is:

1. Configure `ARTSFOLIO_INSTAGRAM_REDIRECT_URI`, `ARTSFOLIO_SOCIAL_TOKEN_KEY`, and `ARTSFOLIO_SOCIAL_STATE_KEY` in `/etc/artsfolio/artsfolio.env`.
2. Deploy through the normal ArtsFolio production deployment workflow.
3. Migration 0070 creates the initial social tables and seeds the dispatcher.
4. Migration 0071 adds account identity binding and multi-account-ready connection uniqueness.
5. Preflight validates source-level integration and migration integrity.
6. Background workers restart through `scripts/deploy/deploy_production.sh`.
7. For each tenant, configure that tenant's Meta App ID/App Secret in Tenant Admin and register the displayed callback URI in its Meta app.
8. Connect a test Creator/Business account and make a controlled publication before broad tenant rollout.

The migrations are additive to existing application data. Tenant-owned Meta app credentials require no schema migration because they use the existing `tenant_settings` key/value store; the App Secret value is encrypted before persistence.

<!-- End of file. -->
