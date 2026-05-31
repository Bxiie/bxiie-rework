<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Http\Support\SessionCookie;
use App\Platform\Identity\PasswordHasher;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Http\Controllers\Auth\LoginController;
use App\Platform\Signup\TenantSignupService;
use App\Support\Security\CsrfTokenService;

/**
 * Handles public platform tenant signup.
 */
final class SignupController
{
    public function __construct(
        private readonly TenantSignupService $signups,
        private readonly PasswordHasher $passwords,
        private readonly CsrfTokenService $csrf,
        private readonly ?SessionRepository $sessions = null,
        private readonly ?SessionTokenService $sessionTokens = null,
    ) {
    }

    public function show(Request $request): Response
    {
        $csrfToken = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');
        $signupCode = htmlspecialchars((string) ($_GET['code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $oauthName = htmlspecialchars((string) ($_SESSION['artsfolio_oauth_profile']['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $oauthEmail = htmlspecialchars((string) ($_SESSION['artsfolio_oauth_profile']['email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $passwordBlock = $oauthEmail !== ''
            ? '<p class="auth-notice">Signed in with SSO as ' . $oauthEmail . '. Choose a site name and slug to create your tenant.</p>'
            : '<label>Password<input type="password" name="password" autocomplete="new-password" minlength="10" required></label>';

        return Response::html(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Create an ArtsFolio site | ArtsFolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/auth.css">
</head>
<body>
<main class="auth-page">
    <section class="auth-card">
        <a href="/" class="auth-logo-link" aria-label="ArtsFolio home">
            <img src="/assets/logo_2.png" alt="ArtsFolio" class="auth-logo">
        </a>
        <p class="auth-eyebrow">Start your site</p>
        <h1>Create an ArtsFolio site</h1>
        <p class="auth-copy">Create the tenant, public subdomain, first owner account, membership, provisioning jobs, and welcome email queue in one flow.</p>
        <div class="sso-row">
            <a href="/auth/google">Continue with Google</a>
            <a href="/auth/facebook">Continue with Facebook</a>
        </div>
        <div class="divider"><span>or continue below</span></div>
        <form method="post" action="/signup" class="auth-form">
            <input type="hidden" name="csrf_token" value="{$csrfToken}">
            <label>Site name<input type="text" name="site_name" required></label>
            <label>Site slug<input type="text" name="slug" pattern="[a-z0-9-]{3,63}" required></label>
            <label>Your name<input type="text" name="admin_name" autocomplete="name" value="{$oauthName}"></label>
            <label>Email<input type="email" name="email" autocomplete="email" value="{$oauthEmail}" required></label>
            <label>Signup passcode<input type="text" name="signup_code" value="{$signupCode}" autocomplete="off"></label>
            {$passwordBlock}
            <button type="submit">Create site</button>
        </form>
        <p class="auth-links"><a href="/login">Already have an account?</a><a href="/help">Need help?</a></p>
    </section>
</main>
</body>
</html>
HTML);
    }

    public function submit(Request $request): Response
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        $password = (string) ($_POST['password'] ?? '');
        $oauthProfile = is_array($_SESSION['artsfolio_oauth_profile'] ?? null) ? $_SESSION['artsfolio_oauth_profile'] : null;

        if ($oauthProfile === null && strlen($password) < 10) {
            return Response::html('<h1>Password too short</h1><p>Use at least 10 characters.</p>', 422);
        }

        if ($oauthProfile !== null && $password === '') {
            $password = bin2hex(random_bytes(24));
        }

        try {
            $result = $this->signups->register(
                slug: (string) ($_POST['slug'] ?? ''),
                siteName: (string) ($_POST['site_name'] ?? ''),
                adminEmail: (string) ($_POST['email'] ?? ''),
                adminName: (string) ($_POST['admin_name'] ?? ''),
                passwordHash: $this->passwords->hash($password),
                signupCode: (string) ($_POST['signup_code'] ?? ''),
            );

            unset($_SESSION['artsfolio_oauth_profile']);
        } catch (\Throwable $e) {
            return Response::html(
                '<h1>Could not create site</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>',
                422,
            );
        }

        $sessionToken = $this->createBrowserSession((int) $result['user_id']);

        $headers = ['Location' => $this->gettingStartedUrl((string) $result['domain'])];

        if ($sessionToken !== '') {
            $headers['Set-Cookie'] = SessionCookie::issueSetCookie($sessionToken, true);
        }

        return new Response('', 302, $headers);
    }

    private function createBrowserSession(int $userId): string
    {
        if ($this->sessions === null || $this->sessionTokens === null) {
            return '';
        }

        $token = $this->sessionTokens->generateToken();
        $hash = $this->sessionTokens->hashToken($token);

        $this->sessions->create(
            sessionHash: $hash,
            userId: $userId,
            tenantId: null,
            ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
            userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null,
        );

        return $token;
    }

    private function isSecureCookie(): bool
    {
        $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'production'));

        if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Builds the post-signup login URL.
     *
     * Production uses HTTPS without a port. Local development may use the PHP
     * built-in server, so tests and browser smoke checks can set:
     *
     *   APP_ENV=local
     *   ARTSFOLIO_LOCAL_DEV_PORT=8080
     */
    private function gettingStartedUrl(string $domain): string
    {
        $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'production'));
        $localPort = trim((string) (getenv('ARTSFOLIO_LOCAL_DEV_PORT') ?: ''));

        if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
            $port = $localPort !== '' ? ':' . $localPort : '';

            return 'http://' . $domain . $port . '/admin/getting-started';
        }

        return 'https://' . $domain . '/admin/getting-started';
    }
}

// End of file.
