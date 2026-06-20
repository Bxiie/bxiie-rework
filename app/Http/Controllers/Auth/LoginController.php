<?php

/**
 * Tenant browser login/logout controller.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Middleware\CurrentUser;
use App\Http\Request;
use App\Http\Response;
use App\Http\Support\SessionCookie;
use App\Http\View\AuthPage;
use App\Platform\Auth\Password\PasswordAuthService;
use App\Platform\Tenancy\TenantContext;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;

/**
 * Handles local password auth for tenant browser sessions.
 */
final class LoginController
{
    public const COOKIE_NAME = 'artsfolio_session';

    public function __construct(
        private readonly PasswordAuthService $passwordAuth,
        private readonly CsrfTokenService $csrf,
        private readonly ?TenantSettingsRepository $settings = null,
    ) {
    }

    public function show(Request $request, ?TenantContext $tenant = null): Response
    {
        $brand = 'ArtsFolio';
        if ($tenant !== null && $this->settings !== null) {
            $brand = $this->settings->get($tenant, 'artist_name', $this->settings->get($tenant, 'site_title', $tenant->name));
        }

        $oauthBaseUrl = $tenant !== null ? $this->platformOAuthBaseUrl() : '';
        $oauthReturnTo = $tenant !== null ? $this->absoluteUrl($request, '/admin') : '';

        return Response::html(AuthPage::login('/login', '', $brand, '/', $this->csrf->getOrCreate(), $tenant === null, $oauthBaseUrl, $oauthReturnTo));
    }

    public function login(Request $request, ?TenantContext $tenant = null): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::invalidCsrf();
        }

        try {
            $result = $this->passwordAuth->login(
                email: trim((string) ($_POST['email'] ?? '')),
                password: (string) ($_POST['password'] ?? ''),
                tenantId: $tenant?->tenantId,
                ipAddress: $request->server('REMOTE_ADDR'),
                userAgent: $request->server('HTTP_USER_AGENT'),
            );
        } catch (\Throwable $e) {
            $brand = 'ArtsFolio';
            if ($tenant !== null && $this->settings !== null) {
                $brand = $this->settings->get($tenant, 'artist_name', $this->settings->get($tenant, 'site_title', $tenant->name));
            }

            return Response::html(
                AuthPage::login('/login', 'Invalid email or password. Please sign in again.', $brand, '/', $this->csrf->getOrCreate(), $tenant === null, $tenant !== null ? $this->platformOAuthBaseUrl() : '', $tenant !== null ? $this->absoluteUrl($request, '/admin') : ''),
                401
            );
        }

        $token = $this->extractSessionToken($result);
        if ($token === '') {
            return Response::html('<h1>Login failed</h1><p>No session token was returned.</p>', 500);
        }

        FlashMessages::success('Signed in.');

        return new Response('', 302, [
            'Location' => '/admin',
            'Set-Cookie' => SessionCookie::loginHeaders($token, true),
        ]);
    }

    public function logout(Request $request): Response
    {
        $rawToken = $request->cookies['artsfolio_session'] ?? $request->cookies['ARTSFOLIO_SESSION'] ?? null;
        if (is_string($rawToken) && $rawToken !== '') {
            $this->passwordAuth->logoutSessionToken($rawToken);
        }

        if (isset($_COOKIE[self::COOKIE_NAME]) && is_string($_COOKIE[self::COOKIE_NAME])) {
            $this->passwordAuth->logoutSessionToken((string) $_COOKIE[self::COOKIE_NAME]);
        }

        $rawToken = $_COOKIE[self::COOKIE_NAME] ?? null;

        FlashMessages::success('Signed out.');

        return new Response('', 302, [
            'Location' => '/login',
            'Set-Cookie' => SessionCookie::logoutHeaders(),
        ]);
    }

    private function platformOAuthBaseUrl(): string
    {
        $configured = rtrim(trim((string) (getenv('ARTSFOLIO_OAUTH_BASE_URL') ?: getenv('ARTSFOLIO_AUTH_BASE_URL') ?: '')), '/');
        if ($configured !== '') {
            return $configured;
        }

        return 'https://artsfol.io';
    }

    private function absoluteUrl(Request $request, string $path): string
    {
        $host = strtolower(trim(explode(':', $request->host(), 2)[0]));
        if ($host === '') {
            $host = 'artsfol.io';
        }

        $scheme = $this->isSecureRequest($request) ? 'https' : 'http';
        return $scheme . '://' . $host . $path;
    }

    private function isSecureRequest(Request $request): bool
    {
        return strtolower((string) $request->server('HTTP_X_FORWARDED_PROTO', '')) === 'https'
            || strtolower((string) $request->server('HTTPS', '')) === 'on'
            || (string) $request->server('HTTPS', '') === '1';
    }

    private function extractSessionToken(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        if (is_array($result)) {
            foreach (['session_token', 'token', 'plain_token', 'sessionToken'] as $key) {
                if (isset($result[$key]) && is_string($result[$key])) {
                    return $result[$key];
                }
            }
        }

        if (is_object($result)) {
            foreach (['sessionToken', 'session_token', 'token', 'plainToken'] as $property) {
                if (isset($result->{$property}) && is_string($result->{$property})) {
                    return $result->{$property};
                }
            }
        }

        return '';
    }
}

// End of file.
