<?php

declare(strict_types=1);

/**
 * Confirms browser login no longer passes unsupported tenant arg to PasswordAuthService::login.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$controller = file_get_contents($root . '/app/Http/Controllers/Auth/LoginController.php');
$index = file_get_contents($root . '/public/index.php');

if ($controller === false || $index === false) {
    fwrite(STDERR, "Could not read login files.\n");
    exit(1);
}

if (str_contains($controller, 'tenant:') || str_contains($controller, 'TenantContext')) {
    fwrite(STDERR, "LoginController still references tenant context for PasswordAuthService login.\n");
    exit(1);
}

if (str_contains($index, '->login($request, $tenant)')) {
    fwrite(STDERR, "public/index.php still passes tenant into browser login.\n");
    exit(1);
}

echo "Browser login no-tenant-param smoke test passed.\n";

// End of file.
