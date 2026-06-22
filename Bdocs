# ArtsFolio Facebook and Google Authentication Implementation Guide

This guide turns the existing ArtsFolio social-login stubs into a production-ready browser login flow for Google and Facebook.

## Confirmed / Assumed / Unknown

### Confirmed from the bundle

- The current project already mounts these routes in `public/index.php`:
  - `GET /auth/google`
  - `GET /auth/google/callback`
  - `GET /auth/facebook`
  - `GET /auth/facebook/callback`
- `app/Http/Controllers/Auth/OAuthController.php` currently builds provider authorization redirects, but callback handling returns HTTP 501.
- The current redirect method generates a `state` value but does not persist it. That means the callback cannot safely validate `state` yet. Fix this before enabling social login.
- `user_identities` is already modeled through `App\Platform\Identity\UserIdentityRepository` with provider values suitable for `google` and `facebook`.
- Browser sessions already exist through `App\Platform\Auth\Session\SessionRepository`, `SessionTokenService`, and `App\Http\Support\SessionCookie`.
- Platform settings currently store OAuth fields, but `OAuthController` reads client IDs from environment variables.

### Assumed

- Canonical production host for OAuth callbacks is `https://artsfol.io`.
- Production app root is `/var/www/artsfolio`.
- Production environment file is `/etc/artsfolio/artsfolio.env`.
- Deployment still happens through Git from the workstation, not manual server edits.
- For this server-rendered PHP app, use OAuth 2.0 authorization-code flow, not implicit flow or JavaScript SDK login.

### Unknown

- Whether you want social login to create a new global account automatically, or only link to an existing account by verified email.
- Whether new social-login users should land in platform signup, tenant signup, `/platform/admin`, `/admin`, or `/account`.
- Whether platform admin users should be allowed to authenticate with social login at launch. The conservative launch setting is: social login may create a regular user session, but platform-admin authorization still depends on existing membership/role checks.

## Production policy decisions before coding

Use these defaults unless you intentionally choose otherwise:

1. OAuth callbacks always terminate on `https://artsfol.io`, not tenant custom domains.
2. Provider secrets live in `/etc/artsfolio/artsfolio.env`, not in `platform_settings`.
3. `platform_settings` may store client IDs for display/status only, but secrets should be read from env.
4. Do not store Google or Facebook access tokens unless ArtsFolio needs to call their APIs later. For login, the app only needs provider subject, email, display name, and verification status.
5. Only link a social identity to an existing ArtsFolio account when the provider email is verified.
6. If Facebook does not return email, fail cleanly and ask the user to use password login or another provider.
7. Keep local password login enabled.
8. Fail closed on any provider error, state mismatch, missing code, token-exchange failure, profile-fetch failure, unverified email, or ambiguous account match.

## Provider console setup

### Google Cloud Console

Create an OAuth client for a web application.

Authorized JavaScript origins:

```text
https://artsfol.io
```

Authorized redirect URIs:

```text
https://artsfol.io/auth/google/callback
```

Required scopes:

```text
openid email profile
```

The callback `redirect_uri` sent by ArtsFolio must exactly match the URI registered in Google Cloud Console. Google documents `redirect_uri_mismatch` as a failure when the request URI does not match an authorized redirect URI.

Official references:

- https://developers.google.com/identity/protocols/oauth2/web-server
- https://developers.google.com/identity/openid-connect/openid-connect
- https://developers.google.com/identity/protocols/oauth2/policies

### Meta / Facebook Developers

Create or select the ArtsFolio app, add Facebook Login, and configure Client OAuth Settings.

Valid OAuth Redirect URI:

```text
https://artsfol.io/auth/facebook/callback
```

App domains:

```text
artsfol.io
```

Required permissions:

```text
email
public_profile
```

Facebook returns a `code` to the redirect URL after the login dialog. That code must be exchanged server-side for an access token before the app fetches the profile.

Official references:

- https://developers.facebook.com/documentation/facebook-login/guides/advanced/manual-flow
- https://developers.facebook.com/documentation/facebook-login/web
- https://developers.facebook.com/documentation/facebook-login/guides/access-tokens

## Production environment configuration

Edit the production env file on the server only through the existing deployment/secrets process.

```bash
sudo install -m 0640 -o root -g www-data /etc/artsfolio/artsfolio.env /etc/artsfolio/artsfolio.env.before-social-auth.$(date +%Y%m%d%H%M%S)

sudoedit /etc/artsfolio/artsfolio.env
```

Add these names:

```bash
ARTSFOLIO_AUTH_BASE_URL=https://artsfol.io
ARTSFOLIO_GOOGLE_CLIENT_ID=replace-with-google-client-id
ARTSFOLIO_GOOGLE_CLIENT_SECRET=replace-with-google-client-secret
ARTSFOLIO_FACEBOOK_CLIENT_ID=replace-with-facebook-app-id
ARTSFOLIO_FACEBOOK_CLIENT_SECRET=replace-with-facebook-app-secret
```

Restart PHP/app service after deployment if the runtime loads env at process start. If Caddy proxies to PHP-FPM, restart PHP-FPM. If this app is running through Apache mod_php, restart Apache. If it is running through the built-in PHP server during smoke tests, restart that process.

Example production checks:

```bash
cd /var/www/artsfolio
set -a
. /etc/artsfolio/artsfolio.env
set +a

printf 'Google client id set: '; test -n "${ARTSFOLIO_GOOGLE_CLIENT_ID:-}" && echo yes || echo no
printf 'Facebook client id set: '; test -n "${ARTSFOLIO_FACEBOOK_CLIENT_ID:-}" && echo yes || echo no
printf 'Auth base URL: %s\n' "${ARTSFOLIO_AUTH_BASE_URL:-unset}"
```

Do not print secrets.

## Files to change

### 1. `app/Http/Controllers/Auth/OAuthController.php`

Replace the stub with a real controller that:

- accepts only `google` and `facebook` as providers;
- reads provider config from env;
- stores `state` and intended return path in a short-lived server-side session before redirecting;
- validates `state` on callback;
- exchanges authorization code for provider token;
- fetches the provider profile;
- normalizes identity to `{provider, subject, email, email_verified, display_name}`;
- finds or creates the ArtsFolio user;
- links the provider identity in `user_identities`;
- creates a normal `user_sessions` row;
- issues the existing `artsfolio_session` cookie through `SessionCookie::loginHeaders()`;
- audits success and failure where `AuditLogRepository` is available;
- redirects to the stored safe local return path.

Recommended constructor dependencies:

```php
use App\Http\Support\SessionCookie;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Identity\UserIdentityRepository;
use App\Platform\Identity\UserRepository;
use App\Support\Security\CsrfTokenService;
```

The current constructor takes `TenantSignupService`. Replace that dependency. Social login is account authentication; tenant creation can happen after login through the existing signup flow.

### 2. `public/index.php`

Update the four OAuth route factories to pass the new dependencies.

Current shape:

```php
$router->get('/auth/google', fn (Request $request): Response => (new OAuthController(new TenantSignupService($pdo)))->redirect($request, 'google'));
```

Target shape:

```php
$router->get('/auth/google', fn (Request $request): Response => (new OAuthController(
    new UserRepository($pdo),
    new UserIdentityRepository($pdo),
    new SessionRepository($pdo),
    new SessionTokenService(),
    new CsrfTokenService(),
    new AuditLogRepository($pdo),
))->redirect($request, 'google'));
```

Use the same factory for callback and Facebook routes. If `AuditLogRepository` has a different constructor in the current tree, match the current project class exactly.

### 3. `app/Platform/Identity/UserIdentityRepository.php`

Add one safe helper if it does not already exist:

```php
public function findByUserAndProvider(int $userId, string $provider): ?array
{
    $stmt = $this->pdo->prepare(
        "SELECT * FROM user_identities
         WHERE user_id = :user_id AND provider = :provider
         LIMIT 1"
    );

    $stmt->execute([
        'user_id' => $userId,
        'provider' => $provider,
    ]);

    $row = $stmt->fetch();

    return $row ?: null;
}
```

Use this to avoid duplicate identity rows when a user logs in repeatedly.

### 4. `PROJECT_STATE.md`

Add a concise dated entry after implementation:

```markdown
## 2026-06-15 Google and Facebook browser authentication

- Implemented production OAuth authorization-code callback handling for Google and Facebook login.
- OAuth provider secrets are read from `/etc/artsfolio/artsfolio.env` using `ARTSFOLIO_GOOGLE_CLIENT_ID`, `ARTSFOLIO_GOOGLE_CLIENT_SECRET`, `ARTSFOLIO_FACEBOOK_CLIENT_ID`, `ARTSFOLIO_FACEBOOK_CLIENT_SECRET`, and `ARTSFOLIO_AUTH_BASE_URL`.
- OAuth callbacks terminate on `https://artsfol.io/auth/{provider}/callback`; tenant custom domains are not callback hosts.
- Social login creates normal ArtsFolio browser sessions through `user_sessions` and `artsfolio_session` cookies.
- Provider access tokens are not persisted.
```

Do not include secret values.

### 5. Documentation

Update or create:

- `docs/dev/oauth-provider-guide.md`
- `docs/admin/social-authentication.md`
- `docs/user/account-signin.md`

Admin docs should explain provider setup, env names, callback URLs, and rollout checks. User docs should say users can sign in with Google/Facebook when enabled, and that email availability is required.

## Callback algorithm

### Redirect

1. Validate provider.
2. Build callback URL from `ARTSFOLIO_AUTH_BASE_URL`, not from the current tenant/custom-domain host.
3. Generate random `state`.
4. Store `state`, provider, created timestamp, and safe return path in PHP session or another short-lived server-side store.
5. Redirect to provider authorization endpoint.

Provider authorization endpoints:

```text
Google:   https://accounts.google.com/o/oauth2/v2/auth
Facebook: https://www.facebook.com/v25.0/dialog/oauth
```

Google authorization params:

```text
client_id
redirect_uri
response_type=code
scope=openid email profile
state
access_type=online
prompt=select_account
```

Facebook authorization params:

```text
client_id
redirect_uri
response_type=code
scope=email,public_profile
state
```

### Callback

1. Validate provider.
2. Reject `error` responses with HTTP 401 or 502 and a friendly message.
3. Require `code` and `state`.
4. Compare callback `state` with the server-side stored state using `hash_equals()`.
5. Expire the stored state immediately after validation.
6. Exchange `code` for an access token.
7. Fetch profile.
8. Normalize profile.
9. Require provider subject.
10. Require email for account creation/linking.
11. For Google, require `email_verified === true`.
12. For Facebook, treat email as usable only when returned by Graph API for the authenticated user.
13. Find existing identity by provider + subject.
14. If found, load that user.
15. If not found, find existing user by verified email.
16. If user exists, link provider identity unless already linked.
17. If user does not exist, create user and provider identity.
18. Create browser session.
19. Issue cookie.
20. Redirect to safe local return path.

## Token exchange details

### Google token exchange

Endpoint:

```text
POST https://oauth2.googleapis.com/token
```

Form fields:

```text
code
client_id
client_secret
redirect_uri
grant_type=authorization_code
```

Profile source options:

1. Preferred: validate and parse `id_token` claims.
2. Simpler first pass: call Google userinfo endpoint with bearer access token.

Userinfo endpoint:

```text
GET https://openidconnect.googleapis.com/v1/userinfo
```

Required normalized fields:

```text
provider = google
subject = sub
email = email
email_verified = email_verified
display_name = name
```

### Facebook token exchange

Endpoint:

```text
GET https://graph.facebook.com/v25.0/oauth/access_token
```

Query fields:

```text
client_id
client_secret
redirect_uri
code
```

Profile endpoint:

```text
GET https://graph.facebook.com/me?fields=id,name,email
```

Required normalized fields:

```text
provider = facebook
subject = id
email = email
display_name = name
```

If `email` is missing, do not create an account. Show a friendly message telling the user to use Google or password login.

## Session creation pattern

Use the same session machinery as password login:

```php
$plainToken = $this->tokens->generateToken();
$sessionHash = $this->tokens->hashToken($plainToken);
$sessionId = $this->sessions->create(
    sessionHash: $sessionHash,
    userId: $userId,
    tenantId: null,
    ipAddress: $request->server('REMOTE_ADDR'),
    userAgent: $request->server('HTTP_USER_AGENT'),
    ttlSeconds: 2592000,
);

return new Response('', 302, [
    'Location' => $returnTo,
    'Set-Cookie' => SessionCookie::loginHeaders($plainToken, true),
]);
```

Do not invent a second cookie.

## Security traps to avoid

- Do not enable callback routes until `state` is persisted and validated.
- Do not derive callback base URL from `HTTP_HOST`; that breaks custom domains and opens the door to bad redirect behavior. Use `ARTSFOLIO_AUTH_BASE_URL=https://artsfol.io`.
- Do not accept arbitrary `return_to` URLs. Only allow local paths beginning with `/`, and reject `//evil.example`.
- Do not store provider access tokens unless there is a product requirement.
- Do not treat Facebook as verified email if no email is returned.
- Do not let social login bypass platform/tenant role checks.
- Do not put secrets in `PROJECT_STATE.md`, docs, Git, logs, audit details, or rendered admin pages.
- Do not use OAuth implicit flow for this PHP server-rendered app.
- Do not register tenant custom domains as callback URLs for the first implementation.
- Do not leave 501 callback copy in place after the feature is enabled.

## Rollout sequence

Run this on the workstation:

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio

git status --short

git checkout -b social-auth-google-facebook
```

Make code/docs changes, then run:

```bash
php -l app/Http/Controllers/Auth/OAuthController.php
php -l app/Platform/Identity/UserIdentityRepository.php
php -l public/index.php

php scripts/test/platform_contact_management_static.php || true
php scripts/test/preflight.php || true
./scripts/test/preflight.sh
```

Use whatever preflight command is canonical in the current branch. If `./scripts/test/preflight.sh` complains that expected test wiring is missing, fix the preflight registry instead of bypassing it.

Commit and push:

```bash
git diff --check
git status --short
git add app/Http/Controllers/Auth/OAuthController.php \
        app/Platform/Identity/UserIdentityRepository.php \
        public/index.php \
        docs/dev/oauth-provider-guide.md \
        docs/admin/social-authentication.md \
        docs/user/account-signin.md \
        PROJECT_STATE.md

git commit -m "Implement Google and Facebook browser authentication"
git push origin social-auth-google-facebook
```

Deploy through the existing Git-based production flow only.

On production after deploy:

```bash
cd /var/www/artsfolio

git status --short
php -l app/Http/Controllers/Auth/OAuthController.php
php -l app/Platform/Identity/UserIdentityRepository.php
php -l public/index.php

set -a
. /etc/artsfolio/artsfolio.env
set +a

curl -I https://artsfol.io/auth/google
curl -I https://artsfol.io/auth/facebook
```

Expected result: both `curl -I` calls return `302` with a `Location` header pointing to Google or Facebook. Browser callback testing must be done in a real browser because provider redirects, state, and cookies are part of the flow.

## Manual browser verification

1. Open a private browser window.
2. Go to `https://artsfol.io/login`.
3. Click `Continue with Google`.
4. Complete Google login with a test user.
5. Confirm redirect back to ArtsFolio.
6. Confirm `artsfolio_session` cookie exists, is `HttpOnly`, and is `Secure` on HTTPS.
7. Confirm `user_identities` has one `google` row.
8. Log out.
9. Repeat login and confirm a duplicate identity row is not created.
10. Repeat for Facebook.
11. Try a direct callback with a bad state and confirm it fails.
12. Try a callback with no code and confirm it fails.

Database checks:

```bash
mysql \
  -h "$DB_HOST" \
  -P "${DB_PORT:-3306}" \
  -u "$DB_USERNAME" \
  -p"$DB_PASSWORD" \
  "$DB_DATABASE" <<'SQL'
SELECT provider, COUNT(*) AS identities
FROM user_identities
WHERE provider IN ('google', 'facebook')
GROUP BY provider;

SELECT COUNT(*) AS active_social_sessions
FROM user_sessions s
JOIN user_identities ui ON ui.user_id = s.user_id
WHERE ui.provider IN ('google', 'facebook')
  AND s.revoked_at IS NULL
  AND s.expires_at > CURRENT_TIMESTAMP;
SQL
```

## Suggested automated tests

Add a focused static/runtime test such as:

```text
scripts/test/social_auth_static.php
```

It should verify:

- `OAuthController` no longer contains `OAuth callback pending`.
- `OAuthController` uses `hash_equals` for state validation.
- `OAuthController` uses `ARTSFOLIO_AUTH_BASE_URL`.
- `OAuthController` references Google token endpoint.
- `OAuthController` references Facebook token endpoint.
- `OAuthController` calls `SessionCookie::loginHeaders`.
- `OAuthController` does not write provider access tokens to the database.
- `public/index.php` wires `OAuthController` with `UserRepository`, `UserIdentityRepository`, `SessionRepository`, and `SessionTokenService`.

Also add HTTP smoke coverage where practical:

- no client ID returns 501 or a controlled configuration error;
- configured client ID returns 302 to provider;
- bad callback state returns 400/401;
- callback without code returns 400/401;
- mocked provider exchange can create a session in a local test harness.

## Rollback

If login breaks after deployment:

1. Disable social login links in the UI or remove provider env values.
2. Revert the Git commit.
3. Redeploy through the normal deploy path.
4. Restart the PHP runtime if needed.
5. Confirm local password login still works.

Commands:

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio

git revert <social-auth-commit-sha>
git push origin <deployment-branch>
```

Then deploy from Git on production.

## Definition of done

- `/auth/google` redirects to Google when configured.
- `/auth/facebook` redirects to Facebook when configured.
- Callback state is persisted, validated, and expired.
- Google login creates or links a user with verified email.
- Facebook login creates or links a user only when email is returned.
- Normal ArtsFolio browser session is issued.
- Platform and tenant authorization checks still decide access after login.
- Provider access tokens are not persisted.
- Docs and `PROJECT_STATE.md` are updated.
- Preflight passes.
- Browser test confirms no duplicate identity rows on repeated login.

<!-- End of file. -->
