<?php

/**
 * Static regression checks for auth and artwork follow-up behavior.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function af_read(string $path): string
{
    if (!is_file($path)) {
        fwrite(STDERR, "FAILED: missing file {$path}\n");
        exit(1);
    }

    return (string) file_get_contents($path);
}

function af_assert_contains(string $description, string $haystack, string $needle): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAILED: {$description}\nMissing: {$needle}\n");
        exit(1);
    }

    echo "OK: {$description}\n";
}

$passwordAuth = af_read($root . '/app/Platform/Auth/Password/PasswordAuthService.php');
af_assert_contains(
    'password auth service can revoke cookie token',
    $passwordAuth,
    'public function logoutSessionToken(string $rawToken): void'
);
af_assert_contains(
    'password auth logout hashes raw cookie token before revoke',
    $passwordAuth,
    '$this->sessions->revokeByHash($this->tokens->hashToken($rawToken));'
);

$login = af_read($root . '/app/Http/Controllers/Auth/LoginController.php');
af_assert_contains(
    'tenant logout revokes server-side user session before clearing cookie',
    $login,
    '$this->passwordAuth->logoutSessionToken((string) $_COOKIE[self::COOKIE_NAME]);'
);

$index = af_read($root . '/public/index.php');
af_assert_contains(
    'platform password forgot route is registered',
    $index,
    "->get('/password/forgot'"
);

echo "Auth/artwork follow-up static checks passed.\n";

// End of file.
