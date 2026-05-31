<?php

/**
 * Regression test for browser session persistence SQL.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$repository = file_get_contents($root . '/app/Platform/Auth/Session/SessionRepository.php');
$login = file_get_contents($root . '/app/Http/Controllers/Auth/LoginController.php');
$signup = file_get_contents($root . '/app/Http/Controllers/Platform/SignupController.php');

if ($repository === false || $login === false || $signup === false) {
    fwrite(STDERR, "Could not read files required by session regression test.
");
    exit(1);
}

if (str_contains($repository, 'INTERVAL :ttl_seconds SECOND')) {
    fwrite(STDERR, "SessionRepository still uses a bound placeholder inside MariaDB INTERVAL syntax.
");
    exit(1);
}

foreach ([':expires_at', 'private function expiryTimestamp', 'DateTimeImmutable'] as $snippet) {
    if (!str_contains($repository, $snippet)) {
        fwrite(STDERR, "Missing session expiry regression marker: {$snippet}
");
        exit(1);
    }
}

foreach ([$login, $signup] as $source) {
    if (!str_contains($source, "'Set-Cookie'")) {
        fwrite(STDERR, "A browser-auth controller still creates a session without returning Set-Cookie.
");
        exit(1);
    }
}

echo "Session persistence regression test passed.
";

// End of file.
