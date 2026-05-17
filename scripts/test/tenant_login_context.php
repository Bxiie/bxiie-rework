<?php

declare(strict_types=1);

/**
 * Smoke test that tenant browser login POST passes TenantContext.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$controller = file_get_contents($root . '/app/Http/Controllers/Auth/LoginController.php');
$index = file_get_contents($root . '/public/index.php');

if ($controller === false || $index === false) {
    fwrite(STDERR, "Could not read login files.\n");
    exit(1);
}

if (!str_contains($controller, '?TenantContext $tenant = null') || !str_contains($controller, 'tenant: $tenant')) {
    fwrite(STDERR, "LoginController does not accept/pass tenant context.\n");
    exit(1);
}

$tenantPart = explode('if ($tenant) {', $index, 2)[1] ?? '';

if (!str_contains($tenantPart, '->login($request, $tenant)')) {
    fwrite(STDERR, "Tenant POST /login does not pass tenant context.\n");
    exit(1);
}

echo "Tenant login context smoke test passed.\n";

// End of file.
