<?php

declare(strict_types=1);

/**
 * Smoke test for tenant getting-started onboarding wiring.
 */

$root = dirname(__DIR__, 2);

$requiredFiles = [
    $root . '/app/Http/Controllers/Tenant/Admin/GettingStartedController.php',
    $root . '/app/Http/Controllers/Platform/SignupController.php',
    $root . '/public/index.php',
];

foreach ($requiredFiles as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing onboarding file: {$file}\n");
        exit(1);
    }
}

$index = file_get_contents($root . '/public/index.php');
$signup = file_get_contents($root . '/app/Http/Controllers/Platform/SignupController.php');

if ($index === false || $signup === false) {
    fwrite(STDERR, "Could not read onboarding files.\n");
    exit(1);
}

foreach ([
    "TenantAdminGettingStartedController",
    "\$router->get('/admin/getting-started'",
] as $needle) {
    if (!str_contains($index, $needle)) {
        fwrite(STDERR, "Missing onboarding route fragment: {$needle}\n");
        exit(1);
    }
}

foreach ([
    "createBrowserSession",
    "gettingStartedUrl",
    "/admin/getting-started",
    "LoginController::COOKIE_NAME",
] as $needle) {
    if (!str_contains($signup, $needle)) {
        fwrite(STDERR, "Missing signup onboarding fragment: {$needle}\n");
        exit(1);
    }
}

echo "Tenant getting-started smoke test passed.\n";

// End of file.
