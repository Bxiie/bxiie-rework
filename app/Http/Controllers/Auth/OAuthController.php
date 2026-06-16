<?php

/**
 * Browser OAuth/OIDC login controller for Google and Facebook.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Request;
use App\Http\Response;
use App\Http\Support\SessionCookie;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Identity\UserIdentityRepository;
use App\Platform\Identity\UserRepository;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Support\Flash\FlashMessages;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * Completes server-side social login without storing provider access tokens.
 *
 * Google uses the OAuth 2.0 authorization-code flow with OpenID Connect
 * scopes. Facebook uses the server-side code exchange and Graph /me lookup.
 * Provider callbacks are kept on the platform host so tenant custom domains do
 * not need to be registered in third-party OAuth consoles.
 */
final class OAuthController
{
    private const SESSION_STATE_KEY = 'artsfolio_oauth_states';
    private const SESSION_PROFILE_KEY = 'artsfolio_oauth_profile';
    private const STATE_TTL_SECONDS = 600;
    private const SESSION_TTL_SECONDS = 1209600;
    private const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const FACEBOOK_AUTH_URL = 'https://www.facebook.com/v19.0/dialog/oauth';
    private const FACEBOOK_TOKEN_URL = 'https://graph.facebook.com/v19.0/oauth/access_token';
    private const FACEBOOK_ME_URL = 'https://graph.facebook.com/v19.0/me';

    private readonly UserRepository $users;
    private readonly UserIdentityRepository $identities;
    private readonly SessionRepository $sessions;
    private readonly SessionTokenService $sessionTokens;
    private readonly PlatformSettingsRepository $settings;

    public function __construct(private readonly PDO $pdo)
    {
        $this->users = new UserRepository($pdo);
        $this->identities = new UserIdentityRepository($pdo);
        $this->sessions = new SessionRepository($pdo);
        $this->sessionTokens = new SessionTokenService();
        $this->settings = new PlatformSettingsRepository($pdo);
    }

    public function redirect(Request $request, string $provider): Response
    {
        $provider = $this->normalizeProvider($provider);
        $config = $this->providerConfig($provider);

        if ($config['client_id'] === '' || $config['client_secret'] === '') {
            return Response::html(
                '<h1>OAuth provider not configured</h1><p>Configure the ' . ucfirst($provider) . ' client ID and client secret in Platform Admin → Platform Settings before enabling this login.</p>',
                501,
            );
        }

        $state = $this->createState($provider, $this->safeReturnTo($request->query('return_to')));
        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $this->redirectUri($provider),
            'response_type' => 'code',
            'scope' => $provider === 'google' ? 'openid email profile' : 'email,public_profile',
            'state' => $state,
        ];

        if ($provider === 'google') {
            $params['access_type'] = 'online';
            $params['prompt'] = 'select_account';
        }

        return new Response('', 302, [
            'Location' => $config['auth_url'] . '?' . http_build_query($params),
        ]);
    }

    public function callback(Request $request, string $provider): Response
    {
        $provider = $this->normalizeProvider($provider);
        $error = trim((string) $request->query('error', ''));
        if ($error !== '') {
            return $this->fail('Social login was cancelled or rejected by the provider.', 400);
        }

        try {
            $state = $this->consumeState($provider, (string) $request->query('state', ''));
            $code = trim((string) $request->query('code', ''));
            if ($code === '') {
                throw new RuntimeException('Missing OAuth authorization code.');
            }

            $tokenPayload = $this->exchangeCodeForToken($provider, $code);
            $profile = $this->fetchProviderProfile($provider, $tokenPayload);
            $userId = $this->findOrCreateUser($provider, $profile);
            $sessionToken = $this->createBrowserSession($userId, $request);

            $_SESSION[self::SESSION_PROFILE_KEY] = [
                'provider' => $provider,
                'subject' => $profile['subject'],
                'email' => $profile['email'],
                'name' => $profile['name'],
            ];

            FlashMessages::success('Signed in with ' . ucfirst($provider) . '.');

            return new Response('', 302, [
                'Location' => $this->postLoginLocation($state['return_to'], $userId),
                'Set-Cookie' => SessionCookie::loginHeaders($sessionToken, true),
            ]);
        } catch (\Throwable $e) {
            error_log('ArtsFolio OAuth callback failed for ' . $provider . ': ' . $e->getMessage());
            return $this->fail('Social login failed. Please try again or use email/password login.', 400);
        }
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, ['google', 'facebook'], true)) {
            throw new RuntimeException('Unsupported OAuth provider.');
        }

        return $provider;
    }

    /**
     * @return array{client_id:string, client_secret:string, auth_url:string, token_url:string}
     */
    private function providerConfig(string $provider): array
    {
        $clientId = trim((string) $this->settings->get($provider . '_oauth_client_id', ''));
        $clientSecret = trim((string) $this->settings->get($provider . '_oauth_client_secret', ''));


        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'auth_url' => $provider === 'google' ? self::GOOGLE_AUTH_URL : self::FACEBOOK_AUTH_URL,
            'token_url' => $provider === 'google' ? self::GOOGLE_TOKEN_URL : self::FACEBOOK_TOKEN_URL,
        ];
    }

    private function createState(string $provider, string $returnTo): string
    {
        $this->pruneStates();
        $state = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_STATE_KEY][$state] = [
            'provider' => $provider,
            'return_to' => $returnTo,
            'expires_at' => time() + self::STATE_TTL_SECONDS,
        ];

        return $state;
    }

    /**
     * @return array{provider:string, return_to:string, expires_at:int}
     */
    private function consumeState(string $provider, string $state): array
    {
        $this->pruneStates();
        if ($state === '' || !isset($_SESSION[self::SESSION_STATE_KEY][$state])) {
            throw new RuntimeException('Missing or invalid OAuth state.');
        }

        $entry = $_SESSION[self::SESSION_STATE_KEY][$state];
        unset($_SESSION[self::SESSION_STATE_KEY][$state]);

        if (!is_array($entry) || ($entry['provider'] ?? null) !== $provider || (int) ($entry['expires_at'] ?? 0) < time()) {
            throw new RuntimeException('Expired or mismatched OAuth state.');
        }

        return [
            'provider' => (string) $entry['provider'],
            'return_to' => $this->safeReturnTo((string) ($entry['return_to'] ?? '')),
            'expires_at' => (int) $entry['expires_at'],
        ];
    }

    private function pruneStates(): void
    {
        $states = is_array($_SESSION[self::SESSION_STATE_KEY] ?? null) ? $_SESSION[self::SESSION_STATE_KEY] : [];
        foreach ($states as $state => $entry) {
            if (!is_array($entry) || (int) ($entry['expires_at'] ?? 0) < time()) {
                unset($states[$state]);
            }
        }
        $_SESSION[self::SESSION_STATE_KEY] = $states;
    }

    /**
     * @return array<string,mixed>
     */
    private function exchangeCodeForToken(string $provider, string $code): array
    {
        $config = $this->providerConfig($provider);
        $payload = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'redirect_uri' => $this->redirectUri($provider),
            'grant_type' => 'authorization_code',
        ];

        return $this->jsonRequest('POST', $config['token_url'], $payload);
    }

    /**
     * @param array<string,mixed> $tokenPayload
     * @return array{subject:string, email:string, name:string, email_verified:bool, metadata:array<string,mixed>}
     */
    private function fetchProviderProfile(string $provider, array $tokenPayload): array
    {
        $accessToken = (string) ($tokenPayload['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Provider did not return an access token.');
        }

        if ($provider === 'google') {
            $claims = [];
            if (!empty($tokenPayload['id_token']) && is_string($tokenPayload['id_token'])) {
                $claims = $this->decodeJwtPayload($tokenPayload['id_token']);
                if (($claims['aud'] ?? null) !== $this->providerConfig('google')['client_id']) {
                    throw new RuntimeException('Google ID token audience mismatch.');
                }
                if (!empty($claims['exp']) && (int) $claims['exp'] < time()) {
                    throw new RuntimeException('Google ID token is expired.');
                }
            }

            $profile = $this->jsonRequest('GET', self::GOOGLE_USERINFO_URL, [], ['Authorization: Bearer ' . $accessToken]);
            $subject = (string) ($profile['sub'] ?? $claims['sub'] ?? '');
            $email = strtolower(trim((string) ($profile['email'] ?? $claims['email'] ?? '')));
            $name = trim((string) ($profile['name'] ?? $claims['name'] ?? ''));
            $verified = filter_var($profile['email_verified'] ?? $claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
        } else {
            $profile = $this->jsonRequest('GET', self::FACEBOOK_ME_URL, [
                'fields' => 'id,name,email',
                'access_token' => $accessToken,
            ]);
            $subject = (string) ($profile['id'] ?? '');
            $email = strtolower(trim((string) ($profile['email'] ?? '')));
            $name = trim((string) ($profile['name'] ?? ''));
            $verified = $email !== '';
        }

        if ($subject === '') {
            throw new RuntimeException('Provider profile did not include a stable subject.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Provider profile did not include a valid email address.');
        }

        return [
            'subject' => $subject,
            'email' => $email,
            'name' => $name !== '' ? $name : $email,
            'email_verified' => $verified,
            'metadata' => ['profile' => $profile],
        ];
    }

    /**
     * @param array{subject:string, email:string, name:string, email_verified:bool, metadata:array<string,mixed>} $profile
     */
    private function findOrCreateUser(string $provider, array $profile): int
    {
        $this->pdo->beginTransaction();
        try {
            $identity = $this->identities->findByProviderSubject($provider, $profile['subject']);
            if ($identity) {
                $this->touchIdentity((int) $identity['id'], $profile['email'], $profile['name'], $profile['metadata'], (bool) $profile['email_verified']);
                $this->pdo->commit();
                return (int) $identity['user_id'];
            }

            $user = $this->users->findByEmail($profile['email']);
            $userId = $user ? (int) $user['id'] : $this->users->create($profile['email'], $profile['name'], null);
            $this->identities->addOauthIdentity(
                userId: $userId,
                provider: $provider,
                providerSubject: $profile['subject'],
                email: $profile['email'],
                displayName: $profile['name'],
                metadata: $profile['metadata'],
                verified: (bool) $profile['email_verified'],
            );
            $this->pdo->commit();

            return $userId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function touchIdentity(int $identityId, string $email, string $displayName, array $metadata, bool $verified): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_identities
             SET email = :email,
                 display_name = :display_name,
                 metadata = :metadata,
                 verified_at = COALESCE(verified_at, :verified_at),
                 last_used_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        $stmt->execute([
            'id' => $identityId,
            'email' => $email,
            'display_name' => $displayName,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'verified_at' => $verified ? $this->now() : null,
        ]);
    }

    private function createBrowserSession(int $userId, Request $request): string
    {
        $token = $this->sessionTokens->generateToken();
        $this->sessions->create(
            sessionHash: $this->sessionTokens->hashToken($token),
            userId: $userId,
            tenantId: null,
            ipAddress: $request->server('REMOTE_ADDR'),
            userAgent: $request->server('HTTP_USER_AGENT'),
            ttlSeconds: self::SESSION_TTL_SECONDS,
        );

        return $token;
    }

    private function postLoginLocation(string $returnTo, int $userId): string
    {
        if ($returnTo !== '') {
            return $returnTo;
        }

        if ($this->hasPlatformRole($userId)) {
            return '/platform/admin';
        }
        $tenantAdminLocation = $this->firstTenantAdminLocation($userId);
        if ($tenantAdminLocation !== '') {
            return $tenantAdminLocation;
        }

        return '/signup';
    }

    private function hasPlatformRole(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM role_assignments ra
             JOIN roles r ON r.id = ra.role_id
             WHERE ra.user_id = :user_id
               AND ra.tenant_id IS NULL
               AND r.scope = 'platform'
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    private function firstTenantAdminLocation(int $userId): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT td.hostname
             FROM tenant_memberships tm
             JOIN tenant_domains td ON td.tenant_id = tm.tenant_id
             WHERE tm.user_id = :user_id
               AND tm.status = 'active'
               AND td.status = 'active'
             ORDER BY td.is_primary DESC, td.id ASC
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);

        $host = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($host === '') {
            return '';
        }

        return 'https://' . $host . '/admin';
    }

    /**
     * @return array<string,mixed>
     */
    private function jsonRequest(string $method, string $url, array $params = [], array $headers = []): array
    {
        $method = strtoupper($method);
        if ($method === 'GET' && $params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }

        $headers[] = 'Accept: application/json';
        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 12,
            ],
        ];

        if ($method === 'POST') {
            $body = http_build_query($params);
            $contextOptions['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
            $contextOptions['http']['content'] = $body;
        }

        $body = @file_get_contents($url, false, stream_context_create($contextOptions));
        if ($body === false) {
            throw new RuntimeException('OAuth provider HTTP request failed.');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OAuth provider returned invalid JSON.');
        }
        if (isset($decoded['error'])) {
            $message = is_array($decoded['error']) ? json_encode($decoded['error']) : (string) $decoded['error'];
            throw new RuntimeException('OAuth provider error: ' . $message);
        }

        return $decoded;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            throw new RuntimeException('Malformed ID token.');
        }

        $payload = $parts[1];
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($json === false) {
            throw new RuntimeException('Could not decode ID token payload.');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('ID token payload was not JSON.');
        }

        return $decoded;
    }

    private function redirectUri(string $provider): string
    {
        return $this->baseUrl() . '/auth/' . $provider . '/callback';
    }

    private function baseUrl(): string
    {
        $configured = rtrim(trim((string) $this->settings->get('oauth_auth_base_url', '')), '/');
        if ($configured !== '') {
            return $configured;
        }

        $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') ? 'https' : 'http';
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'artsfol.io'));
        if ($host === '' || str_starts_with($host, 'www.')) {
            $host = 'artsfol.io';
        }

        return $scheme . '://' . $host;
    }

    private function safeReturnTo(?string $returnTo): string
    {
        $returnTo = trim((string) $returnTo);
        if ($returnTo === '' || !str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return '';
        }
        if (preg_match('/[\r\n]/', $returnTo) === 1) {
            return '';
        }

        return $returnTo;
    }

    private function fail(string $message, int $status): Response
    {
        return Response::html(
            '<main class="auth-page"><section class="auth-card"><h1>Social login problem</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p><a href="/login">Return to login</a></p></section></main>',
            $status,
        );
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}

// End of file.
