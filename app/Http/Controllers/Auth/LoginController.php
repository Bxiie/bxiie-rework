<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

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
 * Browser login/logout controller for tenant domains.
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

        return Response::html(AuthPage::login('/login', '', $brand, '/', $this->csrf->getOrCreate()));
    }

    public function login(Request $request): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        try {
            $result = $this->passwordAuth->login(
                email: trim((string) ($_POST['email'] ?? '')),
                password: (string) ($_POST['password'] ?? ''),
                ipAddress: $request->server('REMOTE_ADDR'),
                userAgent: $request->server('HTTP_USER_AGENT'),
            );
        } catch (\Throwable $e) {
            return Response::html('<h1>Invalid login</h1>', 401);
        }

        $token = $this->extractSessionToken($result);
        if ($token === '') {
            return Response::html('<h1>Login failed</h1><p>No session token was returned.</p>', 500);
        }

        $cookie = SessionCookie::issueSetCookie($token, true);

        FlashMessages::success('Signed in.');

        return new Response('', 302, [
            'Location' => '/admin',
            'Set-Cookie' => $cookie,
        ]);
    }

    public function logout(Request $request): Response
    {
        $cookie = SessionCookie::expireSetCookie();

        FlashMessages::success('Signed out.');

        return new Response('', 302, [
            'Location' => '/login',
            'Set-Cookie' => $cookie,
        ]);
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
