# Facebook and Google Authentication Implementation Guide

This guide describes the production path for turning the existing ArtsFolio `/auth/google` and `/auth/facebook` routes into completed social login flows.

## Current app state

The routes are mounted and visible in the login UI:

- `GET /auth/google`
- `GET /auth/google/callback`
- `GET /auth/facebook`
- `GET /auth/facebook/callback`

`app/Http/Controllers/Auth/OAuthController.php` currently starts provider redirects when `ARTSFOLIO_GOOGLE_CLIENT_ID` or `ARTSFOLIO_FACEBOOK_CLIENT_ID` is set, but callback token exchange and user/session creation still return HTTP 501. The database already has social identity concepts through `user_identities`, `oauth_clients`, and bearer-token infrastructure.

## Required configuration names

Store secrets in `/etc/artsfolio/artsfolio.env` or the production secret source loaded before PHP starts. Do not commit values.

```bash
ARTSFOLIO_GOOGLE_CLIENT_ID=""
ARTSFOLIO_GOOGLE_CLIENT_SECRET=""
ARTSFOLIO_FACEBOOK_CLIENT_ID=""
ARTSFOLIO_FACEBOOK_CLIENT_SECRET=""
ARTSFOLIO_AUTH_BASE_URL="https://artsfol.io"
```

The platform settings screen also stores `google_oauth_client_id`, `google_oauth_client_secret`, `facebook_oauth_client_id`, and `facebook_oauth_client_secret`. Pick one canonical runtime source before coding the callback. The safest production default is env for secrets and platform settings for display/status only.

## Provider console setup

### Google

1. In Google Cloud Console, create or select the ArtsFolio project.
2. Configure the OAuth consent screen.
3. Create an OAuth Client ID with application type `Web application`.
4. Add authorized JavaScript origin:
   - `https://artsfol.io`
5. Add authorized redirect URI:
   - `https://artsfol.io/auth/google/callback`
6. Copy the client ID and client secret into the configured secret store.

Official references:

- Google OAuth web server applications: `https://developers.google.com/identity/protocols/oauth2/web-server`
- Google OAuth policy notes for secure redirect URIs: `https://developers.google.com/identity/verification/authentication-policy-compliance`

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
7. Copy the app ID and app secret into the configured secret store.

Official references:

- Facebook Login manual flow: `https://developers.facebook.com/documentation/facebook-login/guides/advanced/manual-flow`
- Facebook Login for the web: `https://developers.facebook.com/documentation/facebook-login/web`

## Callback implementation plan

Implement the callback in `app/Http/Controllers/Auth/OAuthController.php`.

1. Validate `state`.
   - Store a signed or session-bound state value before redirect.
   - Reject missing, mismatched, reused, or expired state.
2. Exchange the `code` for tokens server-side.
   - Google token endpoint: `https://oauth2.googleapis.com/token`
   - Facebook token endpoint: `https://graph.facebook.com/v19.0/oauth/access_token`
3. Fetch or validate identity.
   - Google: verify the ID token claims or call the userinfo endpoint.
   - Facebook: call `/me?fields=id,name,email` with the access token.
4. Require an email address.
   - If provider does not return email, fail with a friendly message and log the provider subject.
5. Normalize provider identity.
   - Provider values should be exactly `google` and `facebook`.
   - Store provider subject, email, display name, verification status, and raw safe metadata.
6. Find or create the local user.
   - Match existing verified local user by normalized email when safe.
   - Link provider identity through `UserIdentityRepository`.
7. Create the normal ArtsFolio browser session.
   - Use the existing password-auth/session path so tenant admin access, platform admin access, logout revocation, and session bridge behavior remain consistent.
8. Redirect safely.
   - Use a relative `return_to` value only.
   - Default to `/platform/admin` for platform admins and `/admin` or `/` for tenant users depending on context.
9. Audit the action.
   - Record provider, user ID, linked/new account status, request IP, user agent, and outcome.
10. Add regression tests.
   - State validation unit test.
   - Provider token exchange mock test.
   - Callback creates session integration test.
   - Existing local user gets linked rather than duplicated.
   - Failed provider response does not create a user.

## Security traps

- Do not use OAuth implicit flow for this server-rendered app.
- Do not accept callback redirects from tenant custom domains unless each provider app is explicitly configured for them. Keep callback handling on `https://artsfol.io`.
- Do not store provider access tokens unless ArtsFolio actually needs ongoing provider API access.
- Do not trust `email_verified` unless it came from a provider claim or endpoint that actually supplies it.
- Do not let social login bypass suspended tenant checks, platform role checks, or logout token revocation.
- Do not expose client secrets in docs, `PROJECT_STATE.md`, browser HTML, or JavaScript.

## Manual verification

```bash
php -l app/Http/Controllers/Auth/OAuthController.php
php scripts/test/auth_architecture.php
php scripts/test/pricing_billing_auth_social_static.php
./scripts/test/preflight.sh
```

Then verify with real provider test users:

```bash
curl -I https://artsfol.io/auth/google
curl -I https://artsfol.io/auth/facebook
```

Both should redirect to provider authorization pages when client IDs are configured. Callback testing must be done through a browser because provider redirects and state/session cookies are part of the flow.

<!-- End of file. -->
