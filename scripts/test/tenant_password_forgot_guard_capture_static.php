<?php

declare(strict_types=1);

/**
 * Regression check for tenant password-forgot guard capture.
 */

$root = dirname(__DIR__, 2);
$routes = (string) file_get_contents($root . '/app/Http/Routes/tenant.php');
$failures = [];

$required = [
    '$router->post(\'/password/forgot\'',
    'use ($pdo, $root, $tenant, $tenantPasswordResetGuard)',
    '$tenantPasswordResetGuard->recipientExists',
    'createResetTokenForTenantEmail',
    'queuePasswordReset',
];

foreach ($required as $marker) {
    if (!str_contains($routes, $marker)) {
        $failures[] = "tenant.php missing marker: {$marker}";
    }
}

$stale = 'use ($pdo, $root, $tenant): Response';
if (str_contains($routes, $stale)) {
    $failures[] = 'Tenant password-forgot closure still omits TenantPasswordResetGuard.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Tenant password-forgot guard capture check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Tenant password-forgot closure captures TenantPasswordResetGuard.\n";

// End of file.
