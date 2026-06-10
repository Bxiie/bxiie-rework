<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Middleware\CurrentUser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AuthPage;
use App\Platform\Audit\AuditLogRepository;
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
        private readonly ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function loginForm(Request $request): Response
    {
        return Response::html(AuthPage::login('/login/password', '', $this->csrf->getOrCreate()));
    }

    public function loginPassword(Request $request): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            $this->auditAuth($request, 'auth.password_login.denied.invalid_csrf', null, [
                'email' => (string) ($_POST['email'] ?? ''),
            ]);

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
            $this->auditAuth($request, 'auth.password_login.failed', null, [
                'email' => $email,
            ]);

            return Response::html('<h1>Login failed</h1><p>Invalid email or password.</p>', 401);
        }

        $this->auditAuth($request, 'auth.password_login.succeeded', (int) $login['user_id'], [
            'email' => $login['email'],
            'session_id' => $login['session_id'],
        ]);

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
        $csrf = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');

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
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <button type="submit">Logout</button>
</form>
</body>
</html>
HTML);
    }

    public function logout(Request $request): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            $this->auditAuth($request, 'auth.logout.denied.invalid_csrf');

            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $rawToken = $_COOKIE[CurrentUser::COOKIE_NAME] ?? null;
        $session = null;

        if (is_string($rawToken) && $rawToken !== '') {
            $sessionHash = $this->tokens->hashToken($rawToken);
            $session = $this->sessions->findActiveByHash($sessionHash);
            $this->sessions->revokeByHash($sessionHash);
        }

        $this->auditAuth(
            request: $request,
            action: 'auth.logout.succeeded',
            userId: $session && isset($session['user_id']) ? (int) $session['user_id'] : null,
            details: [
                'session_id' => $session['id'] ?? null,
            ],
        );

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

    private function auditAuth(
        Request $request,
        string $action,
        ?int $userId = null,
        array $details = [],
    ): void {
        if (!$this->auditLog) {
            return;
        }

        $this->auditLog->record(
            action: $action,
            userId: $userId,
            entityType: 'auth',
            entityId: 'local_password',
            details: $details,
            ipAddress: $request->server('REMOTE_ADDR'),
        );
    }
}

// End of file.
