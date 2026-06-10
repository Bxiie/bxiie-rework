<?php

declare(strict_types=1);

/**
 * Smoke test for browser login PasswordAuthService constructor wiring.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$index = file_get_contents($root . '/public/index.php');

if ($index === false) {
    fwrite(STDERR, "Could not read public/index.php.\n");
    exit(1);
}

$expected = 'new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService())';

if (!str_contains($index, $expected)) {
    fwrite(STDERR, "Browser login PasswordAuthService constructor is not wired with PasswordHasher.\n");
    exit(1);
}

echo "Browser login constructor smoke test passed.\n";

// End of file.
