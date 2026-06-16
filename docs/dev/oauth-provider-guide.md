# Facebook and Google Authentication Implementation Guide

This guide describes the completed ArtsFolio browser login flow for Google and Facebook.

## Current app state

The routes are mounted and active:

- `GET /auth/google`
- `GET /auth/google/callback`
- `GET /auth/facebook`
- `GET /auth/facebook/callback`

`app/Http/Controllers/Auth/OAuthController.php` now performs the provider redirect, validates one-time PHP-session-bound OAuth `state`, exchanges the provider authorization `code` server-side, reads the provider profile, links or creates the global ArtsFolio user, creates a normal `user_sessions` browser session, issues the normal `artsfolio_session` cookie, and redirects the user into the existing platform or tenant flow.

The earlier callback token exchange and user/session creation are implemented. The old HTTP 501 callback placeholder must not return in production.

## Runtime configuration

OAuth provider values are stored in the database in `platform_settings` and are managed at `/platform/admin/platform-settings`. They are not read from `/etc/artsfolio/artsfolio.env`.

Settings keys:

- `oauth_auth_base_url`
- `google_oauth_client_id`
- `google_oauth_client_secret`
- `facebook_oauth_client_id`
- `facebook_oauth_client_secret`

`oauth_auth_base_url` should normally be `https://artsfol.io`. Keep the callback base URL on the platform origin so every tenant, subdomain, and custom domain can use the same provider console callbacks. Secrets are rendered only into the protected Platform Admin settings form; do not copy them into docs, `PROJECT_STATE.md`, logs, screenshots, browser JavaScript, or support tickets.

## Provider console setup

### Google

1. In Google Cloud Console, create or select the ArtsFolio project.
2. Configure the OAuth consent screen.
3. Create an OAuth Client ID with application type `Web application`.
4. Add authorized JavaScript origin:
   - `https://artsfol.io`
5. Add authorized redirect URI:
   - `https://artsfol.io/auth/google/callback`
6. Copy the client ID and client secret into Platform Admin → Platform Settings → OAuth providers.

### Facebook

1. In Meta for Developers, create or select the ArtsFolio app.
2. Add Facebook Login.
3. Configure Client OAuth Settings.
4. Add valid OAuth redirect URI:
   - `https://artsfol.io/auth/facebook/callback`
5. Confirm app domains include:
   - `artsfol.io`
6. Request only the minimum scopes needed for login:
   - `email`
   - `public_profile`
7. Copy the app ID and app secret into Platform Admin → Platform Settings → OAuth providers.

## Login behavior

1. User clicks `Continue with Google` or `Continue with Facebook`.
2. ArtsFolio creates a random state value and stores it in `$_SESSION['artsfolio_oauth_states']` with a 10-minute TTL.
3. Provider redirects back to the platform callback with `code` and `state`.
4. ArtsFolio consumes the state exactly once and rejects missing, expired, reused, or provider-mismatched state.
5. ArtsFolio exchanges the authorization code for a provider access token.
6. ArtsFolio fetches the provider profile.
7. ArtsFolio requires a valid email address.
8. Existing provider identities are reused. New provider identities are linked to an existing user with the same normalized email, or a new global user is created.
9. ArtsFolio creates a normal browser session in `user_sessions` and sends the existing shared session cookie.
10. Redirect target:
    - safe relative `return_to` value when provided;
    - `/platform/admin` for platform admins;
    - the first active tenant domain `/admin` URL for tenant users;
    - `/signup` for users without a tenant yet.

## Security notes

- OAuth state is one-time and session-bound.
- Callback handling should stay on `https://artsfol.io`, not arbitrary custom tenant domains.
- Provider access tokens are not stored because ArtsFolio only needs login identity.
- The controller rejects profiles without a stable subject or valid email.
- Return URLs must be relative paths and must not begin with `//`.
- Login uses the same browser session table and cookie helper as password auth, so logout/session revocation behavior stays aligned.

## Verification

```bash
php -l app/Http/Controllers/Auth/OAuthController.php
php -l public/index.php
php scripts/test/oauth_browser_login_static.php
php scripts/test/auth_architecture.php
php scripts/test/pricing_billing_auth_social_static.php
./scripts/test/preflight.sh
```

Then verify the real provider browser flow using provider test users:

```bash
curl -I https://artsfol.io/auth/google
curl -I https://artsfol.io/auth/facebook
```

Both should return a `302` to the provider when credentials are configured. Without credentials, they should return `501 OAuth provider not configured`.

## Troubleshooting

- `redirect_uri_mismatch`: make `oauth_auth_base_url` in Platform Admin and provider console redirect URIs exactly match `https://artsfol.io/auth/{provider}/callback`.
- Invalid or missing state: browser lost the PHP session cookie, callback used the wrong host, or the user reused an old callback URL.
- Missing email from Facebook: the user may not expose an email to the app. Use password login or another provider.
- Provider HTTP request failed: confirm PHP can make outbound HTTPS requests and either `allow_url_fopen` or the curl extension is available.
- Login succeeds but tenant admin lands oddly: confirm the user has an active `tenant_memberships` row and an active primary row in `tenant_domains`.

<!-- End of file. -->
