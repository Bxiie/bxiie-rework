# Social Authentication Administration

Social login is implemented for the platform-hosted Google and Facebook flows:

- `https://artsfol.io/auth/google`
- `https://artsfol.io/auth/google/callback`
- `https://artsfol.io/auth/facebook`
- `https://artsfol.io/auth/facebook/callback`

The callbacks create the normal ArtsFolio browser session and use the same `artsfolio_session` cookie as password login.

## Required provider callbacks

Configure provider applications with these exact callback URLs:

- `https://artsfol.io/auth/google/callback`
- `https://artsfol.io/auth/facebook/callback`

Do not register tenant custom domains as OAuth callbacks unless the code is deliberately changed to support per-domain callbacks. The current production-safe pattern centralizes callbacks on `artsfol.io`.

## Platform settings

Configure OAuth in Platform Admin → Platform Settings → OAuth providers. Values are stored in the database table `platform_settings`, not `/etc/artsfolio/artsfolio.env`.

Fields:

- OAuth callback base URL, normally `https://artsfol.io`
- Google client ID
- Google client secret
- Facebook client ID
- Facebook client secret

Never paste real client secrets into docs, screenshots, `PROJECT_STATE.md`, tickets, logs, or browser-visible JavaScript.

## Admin verification

After configuration or deploy:

```bash
cd /var/www/artsfolio
php -l app/Http/Controllers/Auth/OAuthController.php
php scripts/test/oauth_browser_login_static.php
curl -I https://artsfol.io/auth/google
curl -I https://artsfol.io/auth/facebook
```

Expected result with credentials: both `curl` commands return `302` provider redirects.

Expected result without credentials: both routes return an ArtsFolio `501 OAuth provider not configured` page.

## Operational traps

- A provider `redirect_uri_mismatch` almost always means the Platform Settings OAuth callback base URL or the provider console callback URL is wrong.
- Social-login users without an existing tenant membership are sent to `/signup` after login.
- Facebook may not provide an email for every account. ArtsFolio rejects those logins because the global user model requires email.
- OAuth state errors indicate a stale callback, wrong host, expired PHP session, or browser cookie issue.

<!-- End of file. -->
