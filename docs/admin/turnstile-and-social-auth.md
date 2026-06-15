# Turnstile and Social Authentication Admin Guide

## Cloudflare Turnstile

ArtsFolio public contact and email signup forms use Cloudflare Turnstile for bot protection.

### Configure platform-wide Turnstile

1. Sign in to Cloudflare.
2. Open Turnstile.
3. Create a widget for ArtsFolio.
4. Add hostnames:
   - `artsfol.io`
   - `*.artsfol.io`
   - Any active custom tenant domains that should use the platform-wide key.
5. Copy the site key and secret key.
6. In ArtsFolio, open `/platform/admin/platform-settings`.
7. Paste the values into:
   - Cloudflare Turnstile site key
   - Cloudflare Turnstile secret key
8. Save settings.

Blank keys keep local and staging forms usable, but production should have both keys configured.

### Configure tenant-specific Turnstile

Use tenant-specific keys when a custom domain is not covered by the platform widget hostnames.

1. Open the tenant site admin.
2. Go to `/admin/settings`.
3. Enter Cloudflare Turnstile site key and secret key.
4. Save settings.
5. Test the public contact form and footer signup form.

Tenant keys override platform keys. Blank tenant keys inherit platform settings.

### Verify Turnstile after deploy

```bash
curl -s https://artsfol.io/contact | grep -E "cf-turnstile|turnstile/v0/api.js"
curl -s https://bxiie.artsfol.io/contact | grep -E "cf-turnstile|turnstile/v0/api.js"
```

If the widget does not render, confirm the site key exists in platform or tenant settings and that the page source includes the Turnstile script.

## Google and Facebook authentication

The login screen can show Google and Facebook sign-in links. Provider redirects are present, but the callback flow still needs token exchange and session creation before these should be advertised as production login options.

### Google setup summary

1. Create a Google OAuth web application client.
2. Authorized JavaScript origin:
   - `https://artsfol.io`
3. Authorized redirect URI:
   - `https://artsfol.io/auth/google/callback`
4. Store client ID and secret in the production secret source.

### Facebook setup summary

1. Create a Meta developer app.
2. Add Facebook Login.
3. App domain:
   - `artsfol.io`
4. Valid OAuth redirect URI:
   - `https://artsfol.io/auth/facebook/callback`
5. Store app ID and app secret in the production secret source.

### Operational warning

Do not enable social login in production until callbacks create the normal ArtsFolio browser session and are covered by tests. Until then, `/auth/google/callback` and `/auth/facebook/callback` should fail closed rather than silently creating partial accounts.

<!-- End of file. -->
