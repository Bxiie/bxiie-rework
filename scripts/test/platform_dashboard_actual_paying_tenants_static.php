<?php

declare(strict_types=1);

/**
 * Static coverage for Platform Admin dashboard actual paying tenants metric.
 */

$root = dirname(__DIR__, 2);
$dashboard = $root . '/app/Http/Controllers/Platform/Admin/DashboardController.php';

if (!is_file($dashboard)) {
    fwrite(STDERR, "Missing dashboard controller: {$dashboard}\n");
    exit(1);
}

$source = file_get_contents($dashboard);

$forbidden = [
    '<?php\n$actualPayingTenantCount',
    'platformDashboardRoot',
    'platform-actual-paying-tenants',
];

foreach ($forbidden as $needle) {
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "DashboardController still contains raw view-style dashboard code: {$needle}\n");
        exit(1);
    }
}

$required = [
    [$source, "metricCard('Actual paying tenants'", 'dashboard must render actual paying tenants metric card'],
    [$source, '$actualPayingTenants', 'dashboard must compute actualPayingTenants'],
    [$source, "'actual_paying_tenants'", 'dashboard metrics array must include actual_paying_tenants'],
    [$source, 'stripe_subscription_id', 'actual paying tenants must require Stripe subscription confirmation'],
    [$source, 'monthly_price_cents > 0', 'actual paying tenants must require paid plan'],
    [$source, 'COALESCE(t.complementary, 0) = 0', 'actual paying tenants must exclude complementary tenants'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Platform dashboard actual paying tenants static checks passed.\n";

// End of file.
