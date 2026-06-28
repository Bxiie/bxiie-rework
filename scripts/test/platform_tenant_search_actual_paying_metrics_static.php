<?php

declare(strict_types=1);

/**
 * Static coverage for Platform Admin tenant search and actual paying tenants metrics.
 */

$root = dirname(__DIR__, 2);

$files = [
    'tenants' => $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php',
    'dashboard' => $root . '/app/Http/Controllers/Platform/Admin/DashboardController.php',
    'billing_health' => $root . '/app/Http/Controllers/Platform/Admin/BillingHealthController.php',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$tenants = file_get_contents($files['tenants']);
$dashboard = file_get_contents($files['dashboard']);
$billingHealth = file_get_contents($files['billing_health']);
$state = file_get_contents($files['state']);

$required = [
    [$tenants, 'platform-tenant-search', 'tenants controller must render tenant search form'],
    [$tenants, 'data-platform-tenant-search-input', 'tenants search must have input hook'],
    [$tenants, 'data-platform-tenant-search-row', 'tenants search must mark rows'],
    [$tenants, 'row.hidden = !match', 'tenants search must hide non-matches'],
    [$tenants, '$tenantSearchQuery', 'tenants search must preserve q value'],
    [$dashboard, "metricCard('Actual paying tenants'", 'dashboard must render Actual paying tenants metric card'],
    [$dashboard, 'actualPayingTenants', 'dashboard must compute actual paying tenants'],
    [$dashboard, 'actual_paying_tenants', 'dashboard metrics must expose actual paying tenants'],
    [$dashboard, 'stripe_subscription_id', 'dashboard count must require Stripe subscription confirmation'],
    [$dashboard, 'monthly_price_cents > 0', 'dashboard count must require paid plan'],
    [$dashboard, 'COALESCE(t.complementary, 0) = 0', 'dashboard count must exclude complementary tenants when column exists'],
    [$billingHealth, 'Actual paying tenants', 'billing health must show Actual paying tenants'],
    [$billingHealth, 'actualPayingTenants', 'billing health must compute actual paying tenants'],
    [$billingHealth, 'stripe_subscription_id', 'billing health count must require Stripe subscription confirmation'],
    [$state, 'Platform tenant search and actual paying tenant metrics', 'PROJECT_STATE must record update'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Platform tenant search and actual paying tenant metrics static checks passed.\n";

// End of file.
