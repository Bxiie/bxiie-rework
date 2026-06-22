<?php

/**
 * Static regression test for tenant login context and session cookie return.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Auth/LoginController.php') ?: '';
$index = file_get_contents($root . '/app/Http/Routes/tenant.php') ?: '';

$checks = [
    'login accepts tenant context' => 'public function login(Request $request, ?TenantContext $tenant = null): Response',
    'login passes tenant id to auth service' => 'tenantId: $tenant?->tenantId',
    'login returns Set-Cookie header' => "'Set-Cookie' => SessionCookie::loginHeaders",
    'logout returns Set-Cookie header' => "'Set-Cookie' => SessionCookie::logoutHeaders",
];

foreach ($checks as $label => $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "Missing {$label}.\n");
        exit(1);
    }
}

if (!str_contains($index, '->login($request, $tenant)')) {
    fwrite(STDERR, "Tenant POST /login route does not pass tenant context.\n");
    exit(1);
}

echo "Tenant login carries tenant context and returns browser session cookies.\n";

// End of file.
