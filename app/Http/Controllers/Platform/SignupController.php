<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Identity\PasswordHasher;
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
    <title>Create an ArtsFolio site</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<main>
    <h1>Create an ArtsFolio site</h1>
    <form method="post" action="/signup">
        <input type="hidden" name="csrf_token" value="{$csrfToken}">
        <p>
            <label>Site name<br>
                <input type="text" name="site_name" required>
            </label>
        </p>
        <p>
            <label>Site slug<br>
                <input type="text" name="slug" pattern="[a-z0-9-]{3,63}" required>
            </label>
        </p>
        <p>
            <label>Your name<br>
                <input type="text" name="admin_name" autocomplete="name">
            </label>
        </p>
        <p>
            <label>Email<br>
                <input type="email" name="email" autocomplete="email" required>
            </label>
        </p>
        <p>
            <label>Password<br>
                <input type="password" name="password" autocomplete="new-password" minlength="10" required>
            </label>
        </p>
        <button type="submit">Create site</button>
    </form>
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

        if (strlen($password) < 10) {
            return Response::html('<h1>Password too short</h1><p>Use at least 10 characters.</p>', 422);
        }

        try {
            $result = $this->signups->register(
                slug: (string) ($_POST['slug'] ?? ''),
                siteName: (string) ($_POST['site_name'] ?? ''),
                adminEmail: (string) ($_POST['email'] ?? ''),
                adminName: (string) ($_POST['admin_name'] ?? ''),
                passwordHash: $this->passwords->hash($password),
            );
        } catch (\Throwable $e) {
            return Response::html(
                '<h1>Could not create site</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>',
                422,
            );
        }

        return new Response('', 302, ['Location' => $this->loginUrl((string) $result['domain'])]);
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
    private function loginUrl(string $domain): string
    {
        $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'production'));
        $localPort = trim((string) (getenv('ARTSFOLIO_LOCAL_DEV_PORT') ?: ''));

        if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
            $port = $localPort !== '' ? ':' . $localPort : '';

            return 'http://' . $domain . $port . '/login';
        }

        return 'https://' . $domain . '/login';
    }
}

// End of file.
