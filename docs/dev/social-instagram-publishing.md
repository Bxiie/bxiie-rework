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

The social provider layer lives under `app/Tenant/Social/`. `SocialRepository` owns tenant-scoped persistence, `SocialPermissionService` owns the `social.publish` capability, `SocialTemplateRenderer` renders caption placeholders, `SocialImageService` creates publication-only JPEG derivatives, `InstagramClient` encapsulates Meta HTTP calls, and `SocialPublishingService` runs due posts from the background worker.

The browser-facing social routes are isolated in `SocialFrontController`, `SocialComposeApiController`, and `SocialPermissionAdminController`. `public/index.php` dispatches those routes before the legacy tenant route registrar and progressively injects `public/assets/social-publishing.js` into ordinary rendered tenant pages.

## Meta application configuration

Instagram publishing uses Instagram API with Instagram Login. The Meta application must be configured for Instagram Creator and Business accounts and must receive production approval/access required by Meta before third-party tenants can publish.

Configure these environment variables in `/etc/artsfolio/artsfolio.env`:

```text
ARTSFOLIO_INSTAGRAM_CLIENT_ID=...
ARTSFOLIO_INSTAGRAM_CLIENT_SECRET=...
ARTSFOLIO_INSTAGRAM_REDIRECT_URI=https://artsfol.io/social/instagram/callback
ARTSFOLIO_SOCIAL_TOKEN_KEY=...
ARTSFOLIO_SOCIAL_STATE_KEY=...
```

Generate the token key:

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

Generate a separate state-signing key:

```bash
openssl rand -hex 32
```

Never commit either secret. `ARTSFOLIO_SOCIAL_TOKEN_KEY` is a long-lived encryption key. Changing or losing it makes existing encrypted Instagram access tokens unreadable and requires tenant reconnection.

The Instagram OAuth callback registered at Meta must exactly match `ARTSFOLIO_INSTAGRAM_REDIRECT_URI`. The current implementation requests `instagram_business_basic` and `instagram_business_content_publish`.

## Security model

ArtsFolio never stores an Instagram password. OAuth access tokens are encrypted with libsodium secretbox before database persistence. Temporary publication media URLs use a random 256-bit token; only the SHA-256 token hash is stored. The media URL is tenant-host-bound and expires after two hours.

Social routes require an active tenant membership. Owners/admins receive publishing permission automatically. Editors require an explicit `tenant_user_permissions` row with `permission_key = 'social.publish'`. The admin-only `/admin/social/editor-permission` endpoint manages this capability.

Social publishing is enabled only when the tenant's current plan slug is `studio`, `pro`, or `collective`.

Internal artwork notes are not available to the template engine. Template placeholders are limited to intentionally publishable artwork/tenant metadata.

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

A submitted social post is a snapshot. `social_posts.snapshot_json`, its rendered caption, hashtags, template ID, and ordered `social_post_items` preserve the submitted state. Later artwork edits do not mutate the scheduled post. An explicit Edit action rewrites the pending snapshot before publishing begins.

Pending states are `draft`, `scheduled`, `failed`, and `authorization_required`. `publishing` is claimed atomically and cannot be edited or canceled. Final successful state is `published`; user cancellation is `cancelled`.

## Background worker

Migration 0070 seeds the first `social.publish_due` background job. `scripts/workers/run_once.php` handles it and always schedules its next iteration through `BackgroundJobRepository::enqueueSingleton()`.

The default dispatcher interval is 60 seconds. Each pass claims due `social_posts` using a conditional status update. This prevents two background workers from intentionally publishing the same post through the ordinary worker path.

Transient provider rate-limit/server failures are retried with bounded backoff. Authentication failures transition to `authorization_required`. Final failure and successful publication both queue email notifications to tenant owners/admins and editors who have `social.publish`.

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
SELECT id, tenant_id, status, scheduled_at, next_attempt_at, publish_attempts,
       remote_post_id, LEFT(last_error, 240) AS last_error
FROM social_posts
ORDER BY id DESC
LIMIT 50;
```

Never print or log `social_connections.token_ciphertext`, access tokens, client secrets, or the social token key.

If all scheduled posts stop, first verify that a `social.publish_due` background job is queued/running and that background workers are healthy. If one tenant reports `authorization_required`, reconnect that tenant's Instagram account instead of globally restarting or rotating credentials.

## Deployment

Production deployment order is:

1. Add the Meta client values and newly generated social keys to `/etc/artsfolio/artsfolio.env`.
2. Register the production OAuth callback with Meta.
3. Deploy the branch through the normal ArtsFolio production deployment workflow.
4. Migration 0070 creates social tables and seeds the dispatcher.
5. Preflight validates source-level integration.
6. Background workers restart through `scripts/deploy/deploy_production.sh`.
7. Connect a test Creator/Business account and make a controlled publication before broad tenant rollout.

The migration is additive. Existing artwork, tenant, sales, email, and media rows are not rewritten except for the two nullable artwork social columns.

<!-- End of file. -->
