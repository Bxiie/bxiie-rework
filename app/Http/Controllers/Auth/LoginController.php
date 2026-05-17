<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Auth\Password\PasswordAuthService;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;

/**
 * Minimal browser login/logout controller for local email/password auth.
 */
final class LoginController
{
    public const COOKIE_NAME = 'artsfolio_session';

    public function __construct(
        private readonly PasswordAuthService $passwordAuth,
        private readonly CsrfTokenService $csrf,
    ) {
    }

    public function show(Request $request): Response
    {
        $csrfToken = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sign in | ArtsFolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<main>
    <h1>Sign in</h1>
    <form method="post" action="/login">
        <input type="hidden" name="csrf_token" value="{$csrfToken}">
        <p><label>Email<br><input type="email" name="email" autocomplete="email" required></label></p>
        <p><label>Password<br><input type="password" name="password" autocomplete="current-password" required></label></p>
        <button type="submit">Sign in</button>
    </form>
</main>
</body>
</html>
HTML);
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

        setcookie(self::COOKIE_NAME, $token, [
            'expires' => time() + 86400 * 14,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        FlashMessages::success('Signed in.');

        return new Response('', 302, ['Location' => '/admin']);
    }

    public function logout(Request $request): Response
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        FlashMessages::success('Signed out.');

        return new Response('', 302, ['Location' => '/login']);
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
