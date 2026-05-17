<?php

declare(strict_types=1);

/**
 * Smoke test for tenant bootstrap script presence and lifecycle template files.
 */

$root = dirname(__DIR__, 2);

$required = [
    $root . '/scripts/tenant/bootstrap_tenant.php',
    $root . '/template/email/lifecycle/tenant_admin_welcome_6h.txt',
    $root . '/template/email/lifecycle/tenant_admin_feature_deep_dive_1d.txt',
    $root . '/template/email/lifecycle/tenant_admin_weekly_checkin.txt',
    $root . '/docs/dev/tenant-bootstrap.md',
    $root . '/docs/admin/tenant-bootstrap.md',
    $root . '/docs/user/tenant-login.md',
];

foreach ($required as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required tenant bootstrap artifact: {$path}\n");
        exit(1);
    }
}

$script = file_get_contents($root . '/scripts/tenant/bootstrap_tenant.php');

if ($script === false || !str_contains($script, 'bootstrap_tenant.php') || !str_contains($script, '--domain')) {
    fwrite(STDERR, "Tenant bootstrap script does not contain expected command metadata.\n");
    exit(1);
}

echo "Tenant bootstrap artifacts smoke test passed.\n";

// End of file.
