<?php
declare(strict_types=1);

/**
 * Suspended and deleted users must not be silently reactivated or authenticated.
 */

$root = dirname(__DIR__, 2);

$files = [
    'app/Platform/ScaleTesting/ScaleTenantFixtureService.php',
    'app/Platform/Auth/Password/PasswordAuthService.php',
    'app/Http/Controllers/Auth/OAuthController.php',
];

$contents = [];

foreach ($files as $relative) {
    $path = $root . '/' . $relative;
    $value = is_file($path) ? file_get_contents($path) : false;

    if ($value === false) {
        fwrite(STDERR, "Platform user status/auth static check failed: missing {$relative}\n");
        exit(1);
    }

    $contents[$relative] = $value;
}

$failures = [];

$scale = $contents['app/Platform/ScaleTesting/ScaleTenantFixtureService.php'];
$password = $contents['app/Platform/Auth/Password/PasswordAuthService.php'];
$oauth = $contents['app/Http/Controllers/Auth/OAuthController.php'];

if (str_contains($scale, '$updates[] = "status = \'active\'";')) {
    $failures[] = 'Scale fixture upserts still reactivate existing users.';
}

foreach ([
    "(\$user['status'] ?? 'active') !== 'active'",
    "throw new \\RuntimeException('Invalid email or password.');",
] as $needle) {
    if (!str_contains($password, $needle)) {
        $failures[] = "PasswordAuthService.php missing {$needle}";
    }
}

foreach ([
    'private function assertUserIsActive(int $userId): void',
    '$this->assertUserIsActive($userId);',
    "(\$user['status'] ?? 'active') !== 'active'",
] as $needle) {
    if (!str_contains($oauth, $needle)) {
        $failures[] = "OAuthController.php missing {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Platform user status/auth static checks failed:\n - "
        . implode("\n - ", $failures)
        . "\n");
    exit(1);
}

fwrite(STDOUT, "Platform user status/auth static checks passed.\n");

/* End of file. */