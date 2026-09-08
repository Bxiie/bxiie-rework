<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Middleware\CurrentUser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;
use App\Tenant\Social\SocialPermissionService;
use App\Tenant\Social\SocialRepository;
use App\Tenant\Social\SocialTokenCipher;
use App\Tenant\Social\TenantInstagramOAuthClient;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Tenant-owned Meta app credentials and Instagram OAuth routing.
 *
 * Each tenant supplies its own Meta App ID and App Secret. The App Secret is
 * encrypted at rest with ArtsFolio's platform social-token key and is never
 * rendered back to the browser. The platform retains only token/state crypto
 * keys plus the shared OAuth callback URI.
 */
final class SocialInstagramCredentialsController
{
    private PDO $pdo;
    private TenantResolver $tenants;
    private TenantSettingsRepository $settings;
    private SocialRepository $social;
    private SocialPermissionService $permissions;
    private CsrfTokenService $csrf;
    private SocialTokenCipher $cipher;

    public function __construct(private readonly string $root)
    {
        $this->pdo = Database::connect($root);
        $this->tenants = new TenantResolver($this->pdo);
        $this->settings = new TenantSettingsRepository($this->pdo);
        $this->social = new SocialRepository($this->pdo);
        $this->permissions = new SocialPermissionService($this->pdo);
        $this->csrf = new CsrfTokenService();
        $this->cipher = new SocialTokenCipher();
    }

    public function handle(Request $request): ?Response
    {
        $path = $request->path();

        if ($request->method() === 'GET' && $path === '/social/instagram/callback') {
            return $this->oauthCallback();
        }

        if (!in_array([$request->method(), $path], [
            ['GET', '/admin/social'],
            ['POST', '/admin/social/app-credentials'],
            ['GET', '/admin/social/connect'],
            ['POST', '/admin/social/disconnect'],
        ], true)) {
            return null;
        }

        $tenant = $this->tenants->resolveFromHost($request->host());
        if (!$tenant) {
            return Response::notFound();
        }

        $currentUser = (new CurrentUser(new SessionRepository($this->pdo), new SessionTokenService()))->resolve($request);
        if (!$this->permissions->canPublish($currentUser, $tenant)) {
            if (!$currentUser) {
                return new Response('', 303, ['Location' => '/login?notice=admin-login-required']);
            }
            return Response::error(403, 'Social publishing permission required.');
        }
        if (!$this->social->paidPlanAllowsSocial($tenant->tenantId)) {
            return Response::error(403, 'Instagram publishing is available on Studio, Professional, and Collective plans.');
        }

        $isTenantAdmin = $this->permissions->isTenantAdmin($currentUser, $tenant);

        return match ([$request->method(), $path]) {
            ['GET', '/admin/social'] => $this->settingsPage($request, $tenant, $isTenantAdmin),
            ['POST', '/admin/social/app-credentials'] => $isTenantAdmin
                ? $this->saveCredentials($tenant)
                : Response::error(403, 'Tenant administrator access is required to change Meta App credentials.'),
            ['GET', '/admin/social/connect'] => $isTenantAdmin
                ? $this->connect($tenant, $currentUser)
                : Response::error(403, 'Tenant administrator access is required to connect Instagram.'),
            ['POST', '/admin/social/disconnect'] => $isTenantAdmin
                ? $this->disconnect($tenant)
                : Response::error(403, 'Tenant administrator access is required to disconnect Instagram.'),
            default => null,
        };
    }

    private function settingsPage(Request $request, TenantContext $tenant, bool $isTenantAdmin): Response
    {
        $base = (new SocialFrontController($this->root))->handle($request);
        if ($base === null || $base->status() !== 200) {
            return $base ?? Response::notFound();
        }

        $body = $base->body();
        $needle = '<section class="admin-card"><h2>Publishing defaults</h2>';
        if ($isTenantAdmin) {
            $card = $this->credentialsCard($tenant);
            if (str_contains($body, $needle)) {
                $body = str_replace($needle, $card . $needle, $body, $count);
            } else {
                $body = str_replace('</main>', $card . '</main>', $body, $count);
            }
        }

        return new Response($body, $base->status(), $base->headers());
    }

    private function credentialsCard(TenantContext $tenant): string
    {
        $clientId = trim((string) $this->settings->get($tenant, 'instagram_client_id', ''));
        $hasSecret = trim((string) $this->settings->get($tenant, 'instagram_client_secret_ciphertext', '')) !== '';
        $notice = trim((string) ($_GET['notice'] ?? ''));
        $noticeHtml = match ($notice) {
            'instagram-app-saved' => '<p class="admin-notice"><strong>Meta App credentials saved.</strong></p>',
            'configure-instagram-app' => '<p class="admin-notice"><strong>Configure this tenant’s Meta App ID and App Secret before connecting Instagram.</strong></p>',
            default => '',
        };
        $secretStatus = $hasSecret ? 'A secret is stored securely. Leave the field blank to keep it.' : 'No App Secret is stored yet.';
        $connect = ($clientId !== '' && $hasSecret)
            ? '<a class="admin-button" href="/admin/social/connect">Connect Instagram</a>'
            : '<button type="button" disabled>Connect Instagram</button>';
        $csrf = $this->e($this->csrf->getOrCreate());

        return '<section class="admin-card"><h2>Meta App credentials</h2>'
            . $noticeHtml
            . '<p>Each ArtsFolio tenant uses its own Meta app. Register the OAuth Redirect URI below in that tenant’s Meta app. The App Secret is encrypted at rest and is never displayed after it is saved.</p>'
            . '<form method="post" action="/admin/social/app-credentials">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<label>Meta App ID<br><input name="instagram_client_id" value="' . $this->e($clientId) . '" maxlength="255" autocomplete="off" required></label>'
            . '<label>Meta App Secret<br><input type="password" name="instagram_client_secret" value="" maxlength="1024" autocomplete="new-password" placeholder="' . ($hasSecret ? 'Leave blank to keep stored secret' : 'Enter Meta App Secret') . '"></label>'
            . '<p class="form-help">' . $this->e($secretStatus) . '</p>'
            . '<label>OAuth Redirect URI<br><input value="' . $this->e($this->redirectUri()) . '" readonly onclick="this.select()"></label>'
            . '<p><button type="submit">Save Meta App credentials</button></p>'
            . '</form><p>' . $connect . '</p></section>';
    }

    private function saveCredentials(TenantContext $tenant): Response
    {
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $clientId = trim((string) ($_POST['instagram_client_id'] ?? ''));
        $clientSecret = trim((string) ($_POST['instagram_client_secret'] ?? ''));
        $existingCiphertext = trim((string) $this->settings->get($tenant, 'instagram_client_secret_ciphertext', ''));

        if ($clientId === '' || strlen($clientId) > 255) {
            return Response::error(422, 'Meta App ID is required and must be at most 255 characters.');
        }
        if ($clientSecret === '' && $existingCiphertext === '') {
            return Response::error(422, 'Meta App Secret is required the first time this tenant is configured.');
        }
        if (strlen($clientSecret) > 1024) {
            return Response::error(422, 'Meta App Secret is too long.');
        }

        try {
            $newCiphertext = $clientSecret !== '' ? $this->cipher->encrypt($clientSecret) : $existingCiphertext;
        } catch (RuntimeException $e) {
            return Response::error(503, 'Instagram credential storage is not ready: ' . $e->getMessage());
        }

        $this->settings->set($tenant, 'instagram_client_id', $clientId);
        $this->settings->set($tenant, 'instagram_client_secret_ciphertext', $newCiphertext);

        return new Response('', 303, ['Location' => '/admin/social?notice=instagram-app-saved']);
    }

    private function connect(TenantContext $tenant, ?array $currentUser): Response
    {
        try {
            [$clientId, $clientSecret] = $this->tenantOauthCredentials($tenant);
            $state = $this->oauthState(
                $tenant->tenantId,
                (int) ($currentUser['user_id'] ?? 0),
                $tenant->slug,
                $tenant->hostname,
            );
            $location = (new TenantInstagramOAuthClient($clientId, $clientSecret))->authorizationUrl($state, $this->redirectUri());
            return new Response('', 303, ['Location' => $location]);
        } catch (RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'Configure this tenant')) {
                return new Response('', 303, ['Location' => '/admin/social?notice=configure-instagram-app']);
            }
            return Response::error(503, 'Instagram connection is not ready: ' . $e->getMessage());
        }
    }

    private function disconnect(TenantContext $tenant): Response
    {
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }
        $this->social->disconnect($tenant->tenantId);
        return new Response('', 303, ['Location' => '/admin/social?notice=instagram-disconnected']);
    }

    private function oauthCallback(): Response
    {
        try {
            $state = $this->decodeOauthState((string) ($_GET['state'] ?? ''));
            $code = trim((string) ($_GET['code'] ?? ''));
            if ($code === '') {
                throw new RuntimeException('Instagram authorization code is missing.');
            }

            $slug = trim((string) ($state['tenant_slug'] ?? ''));
            if ($slug === '' || preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
                throw new RuntimeException('Instagram OAuth tenant is invalid.');
            }
            $tenant = $this->tenants->resolveFromHost($slug . '.artsfol.io');
            if (!$tenant || $tenant->tenantId !== (int) $state['tenant_id']) {
                throw new RuntimeException('Instagram OAuth tenant could not be resolved.');
            }

            [$clientId, $clientSecret] = $this->tenantOauthCredentials($tenant);
            $result = (new TenantInstagramOAuthClient($clientId, $clientSecret))->exchangeCode($code, $this->redirectUri());
            $tokenCiphertext = $this->cipher->encrypt($result['access_token']);
            $this->social->saveConnection(
                $tenant->tenantId,
                'instagram',
                $result['user_id'],
                $result['username'],
                $tokenCiphertext,
                $result['expires_at'],
                'instagram_business_basic,instagram_business_content_publish',
                (int) $state['user_id'],
            );

            $returnHost = strtolower(trim((string) ($state['return_host'] ?? '')));
            $returnTenant = $returnHost !== '' ? $this->tenants->resolveFromHost($returnHost) : null;
            if (!$returnTenant || $returnTenant->tenantId !== $tenant->tenantId) {
                $returnHost = $tenant->hostname;
            }

            return new Response('', 303, ['Location' => 'https://' . $returnHost . '/admin/social?notice=instagram-connected']);
        } catch (Throwable $e) {
            return Response::error(400, 'Instagram connection failed: ' . $e->getMessage());
        }
    }

    /** @return array{0:string,1:string} */
    private function tenantOauthCredentials(TenantContext $tenant): array
    {
        $clientId = trim((string) $this->settings->get($tenant, 'instagram_client_id', ''));
        $ciphertext = trim((string) $this->settings->get($tenant, 'instagram_client_secret_ciphertext', ''));
        if ($clientId === '' || $ciphertext === '') {
            throw new RuntimeException('Configure this tenant’s Meta App ID and App Secret before connecting Instagram.');
        }
        return [$clientId, $this->cipher->decrypt($ciphertext)];
    }

    private function oauthState(int $tenantId, int $userId, string $tenantSlug, string $returnHost): string
    {
        if ($userId < 1) {
            throw new RuntimeException('A signed-in tenant user is required for Instagram authorization.');
        }
        $payload = base64_encode(json_encode([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'tenant_slug' => $tenantSlug,
            'return_host' => strtolower(trim($returnHost)),
            'expires' => time() + 900,
            'nonce' => bin2hex(random_bytes(12)),
        ], JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payload, $this->stateKey());
        return rtrim(strtr($payload, '+/', '-_'), '=') . '.' . $signature;
    }

    private function decodeOauthState(string $state): array
    {
        [$encoded, $signature] = array_pad(explode('.', $state, 2), 2, '');
        $payload = strtr($encoded, '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        if ($encoded === '' || $signature === '' || !hash_equals(hash_hmac('sha256', $payload, $this->stateKey()), $signature)) {
            throw new RuntimeException('Instagram OAuth state is invalid.');
        }
        $decoded = json_decode((string) base64_decode($payload, true), true);
        if (!is_array($decoded)
            || (int) ($decoded['expires'] ?? 0) < time()
            || (int) ($decoded['tenant_id'] ?? 0) < 1
            || (int) ($decoded['user_id'] ?? 0) < 1
        ) {
            throw new RuntimeException('Instagram OAuth state is expired or incomplete.');
        }
        return $decoded;
    }

    private function stateKey(): string
    {
        $configured = trim((string) (getenv('ARTSFOLIO_SOCIAL_STATE_KEY') ?: getenv('ARTSFOLIO_SOCIAL_TOKEN_KEY') ?: ''));
        if ($configured === '') {
            throw new RuntimeException('ARTSFOLIO_SOCIAL_STATE_KEY is not configured.');
        }
        return $configured;
    }

    private function redirectUri(): string
    {
        return trim((string) (getenv('ARTSFOLIO_INSTAGRAM_REDIRECT_URI') ?: 'https://artsfol.io/social/instagram/callback'));
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// End of file.
