<?php

declare(strict_types=1);

/**
 * Static coverage for Platform Admin tenants search and actual paying tenant count.
 */

$root = dirname(__DIR__, 2);

$files = [
    'tenants_controller' => $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php',
    'dashboard_controller' => $root . '/app/Http/Controllers/Platform/Admin/DashboardController.php',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$tenants = file_get_contents($files['tenants_controller']);
$dashboard = file_get_contents($files['dashboard_controller']);
$state = file_get_contents($files['state']);

$required = [
    [$tenants, 'platform-tenant-search', 'tenants page must include search form'],
    [$tenants, 'data-platform-tenant-search-input', 'tenants page must include search input hook'],
    [$tenants, 'data-platform-tenant-search-row', 'tenants page must mark searchable rows'],
    [$tenants, 'row.hidden = !match', 'tenants page must filter rendered rows'],
    [$tenants, '$tenantSearchQuery', 'tenants page must preserve q search value'],
    [$dashboard, 'Actual paying tenants', 'dashboard must show actual paying tenants metric'],
    [$dashboard, 'actualPayingTenants', 'dashboard must compute actual paying tenants'],
    [$dashboard, 'actual_paying_tenants', 'dashboard metrics array must include actual paying tenants'],
    [$dashboard, 'stripe_subscription_id', 'actual paying tenants must require Stripe subscription confirmation'],
    [$dashboard, 'monthly_price_cents > 0', 'actual paying tenants must require paid plan'],
    [$dashboard, 'COALESCE(t.complementary, 0) = 0', 'actual paying tenants must exclude complementary tenants'],
    [$state, 'Platform tenant search and paying tenant count', 'PROJECT_STATE must record tenant search/paying count update'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Platform tenants search and actual paying tenant count static checks passed.\n";

// End of file.
