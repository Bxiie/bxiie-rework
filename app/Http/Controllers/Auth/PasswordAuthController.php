<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Middleware\CurrentUser;
use App\Http\Request;
use App\Http\Response;
use App\Http\Support\SessionCookie;
use App\Http\View\AuthPage;
use App\Http\Support\SessionCookie;
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
        return Response::html(AuthPage::login('/login', '', 'ArtsFolio', '/', $this->csrf->getOrCreate()));
    }

    public function loginPassword(Request $request): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            $this->auditAuth($request, 'auth.password_login.denied.invalid_csrf', null, ['email' => (string) ($_POST['email'] ?? '')]);
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
                ttlSeconds: !empty($_POST['keep_me_logged_in']) ? 2592000 : 86400,
            );
        } catch (\Throwable $e) {
            $this->auditAuth($request, 'auth.password_login.failed', null, ['email' => $email]);
            return Response::html('<h1>Login failed</h1><p>Invalid email or password.</p>', 401);
        }

        $this->auditAuth($request, 'auth.password_login.succeeded', (int) $login['user_id'], ['email' => $login['email'], 'session_id' => $login['session_id']]);

        return new Response('', 302, [
            'Location' => '/platform/admin',
            'Set-Cookie' => SessionCookie::issueHeader($login['session_token'], !empty($_POST['keep_me_logged_in'])),
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
    <title>My ArtsFolio account</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/platform.css">
</head>
<body class="platform-page">
<header class="platform-header"><a class="platform-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a><nav><a href="/admin">Platform admin</a><a href="/directory">Directory</a><a href="/help">Help</a></nav></header>
<main class="platform-main">
<section class="platform-page-heading"><p class="eyebrow">Account</p><h1>My ArtsFolio account</h1><p>This page confirms the active browser session and gives you a clean launch point into platform or tenant administration.</p></section>
<section class="platform-section docs-section"><h2>Signed in as</h2><p><strong>Email:</strong> {$email}</p><p><strong>Display name:</strong> {$displayName}</p><p><a class="button primary" href="/admin">Open platform admin</a> <a class="button secondary" href="/help/getting-started">Read setup help</a></p><form method="post" action="/logout"><input type="hidden" name="csrf_token" value="{$csrf}"><button type="submit">Logout</button></form></section>
</main>
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

        $this->auditAuth($request, 'auth.logout.succeeded', $session && isset($session['user_id']) ? (int) $session['user_id'] : null, ['session_id' => $session['id'] ?? null]);

        return new Response('', 302, ['Location' => '/login', 'Set-Cookie' => SessionCookie::expireHeader()]);
    }

    private function makeSessionCookie(string $token): string
    {
        return SessionCookie::issueHeader($token, true);
    }

    private function expireSessionCookie(): string
    {
        return SessionCookie::expireHeader();
    }

    private function auditAuth(Request $request, string $action, ?int $userId = null, array $details = []): void
    {
        if (!$this->auditLog) {
            return;
        }

        $this->auditLog->record(action: $action, userId: $userId, entityType: 'auth', entityId: 'local_password', details: $details, ipAddress: $request->server('REMOTE_ADDR'));
    }
}

// End of file.
