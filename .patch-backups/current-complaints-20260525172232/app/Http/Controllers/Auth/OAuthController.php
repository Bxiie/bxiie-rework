<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Request;
use App\Http\Response;

/**
 * Starts provider login flows for Google and Facebook.
 *
 * The routes are intentionally wired now. Full callback exchange requires
 * provider client IDs/secrets and redirect URIs in the runtime environment.
 */
final class OAuthController
{
    private const PROVIDERS = ['google', 'facebook'];

    public function start(Request $request, string $provider): Response
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            return Response::notFound('Unknown OAuth provider.');
        }

        $clientId = $this->env($provider, 'CLIENT_ID');
        $clientSecret = $this->env($provider, 'CLIENT_SECRET');

        if ($clientId === '' || $clientSecret === '') {
            return Response::html($this->configurationRequiredPage($provider), 501);
        }

        $state = bin2hex(random_bytes(24));
        $_SESSION['artsfolio_oauth_state_' . $provider] = $state;

        return new Response('', 302, ['Location' => $this->authorizationUrl($provider, $clientId, $state)]);
    }

    public function callback(Request $request, string $provider): Response
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            return Response::notFound('Unknown OAuth provider.');
        }

        $expectedState = (string) ($_SESSION['artsfolio_oauth_state_' . $provider] ?? '');
        $actualState = (string) ($_GET['state'] ?? '');

        if ($expectedState === '' || !hash_equals($expectedState, $actualState)) {
            return Response::html('<h1>Invalid OAuth state</h1><p>Please start sign-in again.</p>', 419);
        }

        return Response::html($this->configurationRequiredPage($provider), 501);
    }

    private function authorizationUrl(string $provider, string $clientId, string $state): string
    {
        $redirectUri = $this->baseUrl() . '/auth/' . $provider . '/callback';

        if ($provider === 'google') {
            return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'access_type' => 'online',
                'prompt' => 'select_account',
            ]);
        }

        return 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'email,public_profile',
            'state' => $state,
        ]);
    }

    private function configurationRequiredPage(string $provider): string
    {
        $safeProvider = htmlspecialchars(ucfirst($provider), ENT_QUOTES, 'UTF-8');
        $upper = strtoupper($provider);

        return <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{$safeProvider} login not configured</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/auth.css"></head>
<body><main class="auth-page"><section class="auth-card"><img src="/assets/logo_2.png" alt="ArtsFolio" class="auth-logo"><h1>{$safeProvider} login is wired but not configured</h1><p class="auth-copy">Set ARTSFOLIO_{$upper}_CLIENT_ID and ARTSFOLIO_{$upper}_CLIENT_SECRET, then configure the provider redirect URI to <code>{$this->baseUrl()}/auth/{$provider}/callback</code>.</p><p class="auth-links"><a href="/signup">Use local signup</a><a href="/login">Use local sign in</a></p></section></main></body>
</html>
HTML;
    }

    private function env(string $provider, string $key): string
    {
        return trim((string) getenv('ARTSFOLIO_' . strtoupper($provider) . '_' . $key));
    }

    private function baseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'artsfol.io';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        return $scheme . '://' . $host;
    }
}

// End of file.
