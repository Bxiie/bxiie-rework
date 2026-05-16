<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Middleware\CurrentUser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Auth\Password\PasswordAuthService;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Support\Security\CsrfTokenService;

/**
 * Handles local email/password browser authentication routes.
 */
final class PasswordAuthController
{
    public function __construct(
        private readonly PasswordAuthService $auth,
        private readonly SessionRepository $sessions,
        private readonly SessionTokenService $tokens,
        private readonly CsrfTokenService $csrf,
    ) {
    }

    public function loginForm(Request $request): Response
    {
        $csrf = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Login | ArtsFolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Login</h1>

<form method="post" action="/login/password">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <p>
        <label>Email<br>
            <input type="email" name="email" autocomplete="email" required>
        </label>
    </p>
    <p>
        <label>Password<br>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
    </p>
    <button type="submit">Sign in</button>
</form>

<p>OAuth/OIDC sign-in buttons will be added here.</p>
</body>
</html>
HTML);
    }

    public function loginPassword(Request $request): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        try {
            $login = $this->auth->login(
                email: $email,
                password: $password,
                tenantId: null,
                ipAddress: $request->server('REMOTE_ADDR'),
                userAgent: $request->server('HTTP_USER_AGENT'),
            );
        } catch (\Throwable $e) {
            return Response::html('<h1>Login failed</h1><p>Invalid email or password.</p>', 401);
        }

        return new Response('', 302, [
            'Location' => '/me',
            'Set-Cookie' => $this->makeSessionCookie($login['session_token']),
        ]);
    }

    public function me(Request $request, ?array $currentUser): Response
    {
        if (!$currentUser) {
            return new Response('', 302, ['Location' => '/login']);
        }

        $email = htmlspecialchars((string) $currentUser['email'], ENT_QUOTES, 'UTF-8');
        $displayName = htmlspecialchars((string) ($currentUser['display_name'] ?? ''), ENT_QUOTES, 'UTF-8');

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Current User | ArtsFolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Current User</h1>
<p>Email: {$email}</p>
<p>Display name: {$displayName}</p>
<form method="post" action="/logout">
    <input type="hidden" name="csrf_token" value="{$this->csrf->getOrCreate()}">
    <button type="submit">Logout</button>
</form>
</body>
</html>
HTML);
    }

    public function logout(Request $request): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $rawToken = $_COOKIE[CurrentUser::COOKIE_NAME] ?? null;

        if (is_string($rawToken) && $rawToken !== '') {
            $this->sessions->revokeByHash($this->tokens->hashToken($rawToken));
        }

        return new Response('', 302, [
            'Location' => '/login',
            'Set-Cookie' => $this->expireSessionCookie(),
        ]);
    }

    private function makeSessionCookie(string $token): string
    {
        return CurrentUser::COOKIE_NAME . '=' . rawurlencode($token)
            . '; Path=/; HttpOnly; SameSite=Lax; Max-Age=1209600';
    }

    private function expireSessionCookie(): string
    {
        return CurrentUser::COOKIE_NAME . '=deleted; Path=/; HttpOnly; SameSite=Lax; Max-Age=0';
    }
}

// End of file.
