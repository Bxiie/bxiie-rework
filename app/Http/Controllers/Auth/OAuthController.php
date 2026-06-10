<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Signup\TenantSignupService;

/**
 * OAuth/OIDC browser entry points for Google and Facebook.
 *
 * The redirect routes are live. Callback tenant creation requires provider
 * credentials and token exchange, so callbacks fail closed with an explicit
 * implementation message rather than pretending OAuth is complete.
 */
final class OAuthController
{
    public function __construct(private readonly TenantSignupService $signups) {}

    public function redirect(Request $request, string $provider): Response
    {
        $provider = strtolower($provider);
        $clientId = getenv('ARTSFOLIO_' . strtoupper($provider) . '_CLIENT_ID') ?: '';
        $redirectUri = $this->baseUrl() . '/auth/' . $provider . '/callback';
        if ($clientId === '') { return Response::html('<h1>OAuth provider not configured</h1><p>Set ARTSFOLIO_' . strtoupper($provider) . '_CLIENT_ID and callback secrets before enabling this login.</p>', 501); }
        $authBase = $provider === 'google' ? 'https://accounts.google.com/o/oauth2/v2/auth' : 'https://www.facebook.com/v19.0/dialog/oauth';
        $scope = $provider === 'google' ? 'openid email profile' : 'email,public_profile';
        $query = http_build_query(['client_id' => $clientId, 'redirect_uri' => $redirectUri, 'response_type' => 'code', 'scope' => $scope, 'state' => bin2hex(random_bytes(16))]);
        return new Response('', 302, ['Location' => $authBase . '?' . $query]);
    }

    public function callback(Request $request, string $provider): Response
    {
        return Response::html('<h1>OAuth callback pending</h1><p>The ' . htmlspecialchars($provider, ENT_QUOTES, 'UTF-8') . ' route is mounted, but provider token exchange and tenant-creation mapping still need credentials and a tested callback implementation.</p>', 501);
    }

    private function baseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'artsfol.io');
    }
}

// End of file.
