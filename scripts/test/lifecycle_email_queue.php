<?php

declare(strict_types=1);

/**
 * Smoke test for lifecycle queue script and required lifecycle templates.
 */

$root = dirname(__DIR__, 2);

$requiredFiles = [
    $root . '/scripts/email/queue_lifecycle_emails.php',
    $root . '/template/email/lifecycle/tenant_admin_welcome_6h.txt',
    $root . '/template/email/lifecycle/tenant_admin_feature_deep_dive_1d.txt',
    $root . '/template/email/lifecycle/tenant_admin_weekly_checkin.txt',
    $root . '/template/email/lifecycle/tenant_admin_cancelled_6h.txt',
    $root . '/template/email/lifecycle/tenant_admin_winback_1w.txt',
    $root . '/template/email/lifecycle/tenant_admin_winback_1m.txt',
];

foreach ($requiredFiles as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing lifecycle artifact: {$file}\n");
        exit(1);
    }
}

$script = file_get_contents($root . '/scripts/email/queue_lifecycle_emails.php');

if ($script === false) {
    fwrite(STDERR, "Could not read lifecycle queue script.\n");
    exit(1);
}

foreach ([
    'tenant_admin_welcome_6h',
    'tenant_admin_feature_deep_dive_1d',
    'tenant_admin_weekly_checkin',
    'tenant_admin_cancelled_6h',
    'tenant_admin_winback_1w',
    'tenant_admin_winback_1m',
] as $templateKey) {
    if (!str_contains($script, $templateKey)) {
        fwrite(STDERR, "Lifecycle queue script missing template key: {$templateKey}\n");
        exit(1);
    }
}

echo "Lifecycle email queue smoke test passed.\n";

// End of file.
