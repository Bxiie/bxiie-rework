<?php

declare(strict_types=1);

/**
 * Smoke test for platform tenant signup service and route wiring.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$files = [
    $root . '/app/Platform/Signup/TenantSignupService.php',
    $root . '/app/Http/Controllers/Platform/SignupController.php',
    $root . '/public/index.php',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing platform signup file: {$file}\n");
        exit(1);
    }
}

$index = file_get_contents($root . '/public/index.php');

if ($index === false) {
    fwrite(STDERR, "Could not read public/index.php.\n");
    exit(1);
}

foreach ([
    "PlatformSignupController",
    "TenantSignupService",
    "\$router->get('/signup'",
    "\$router->post('/signup'",
] as $needle) {
    if (!str_contains($index, $needle)) {
        fwrite(STDERR, "Missing signup route fragment: {$needle}\n");
        exit(1);
    }
}

echo "Platform signup smoke test passed.\n";

// End of file.
