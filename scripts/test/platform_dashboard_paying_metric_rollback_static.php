<?php

declare(strict_types=1);

/**
 * Static coverage for emergency rollback of Platform Admin actual paying tenants metric.
 */

$root = dirname(__DIR__, 2);
$dashboard = $root . '/app/Http/Controllers/Platform/Admin/DashboardController.php';

if (!is_file($dashboard)) {
    fwrite(STDERR, "Missing dashboard controller: {$dashboard}\n");
    exit(1);
}

$source = file_get_contents($dashboard);

$forbidden = [
    "metricCard('Actual paying tenants'",
    '$actualPayingTenants',
    "'actual_paying_tenants'",
    "'actual_paying_detail'",
    'platformDashboardRoot',
    'platform-actual-paying-tenants',
];

foreach ($forbidden as $needle) {
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "DashboardController still contains paying-count rollback target: {$needle}\n");
        exit(1);
    }
}

$required = [
    [$source, "metricCard('Tenants'", 'dashboard must still render Tenants card'],
    [$source, '$paidTenants', 'dashboard must still compute existing paidTenants metric'],
    [$source, "'paid_tenants'", 'dashboard must still expose existing paid_tenants metric'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Platform dashboard paying metric rollback static checks passed.\n";

// End of file.
