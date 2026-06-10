<?php
/**
 * Static regression checks for dashboard schema alignment and billing layout.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    $root . '/app/Http/Controllers/Platform/Admin/DashboardController.php',
    $root . '/app/Http/Controllers/Tenant/Admin/DashboardController.php',
    $root . '/app/Tenant/Sales/SalesRepository.php',
    $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing required file: {$file}\n");
        exit(1);
    }
}

$platformDashboard = file_get_contents($files[0]);
$tenantDashboard = file_get_contents($files[1]);
$salesRepository = file_get_contents($files[2]);
$tenantController = file_get_contents($files[3]);

$checks = [
    [$platformDashboard, 'SHOW TABLES LIKE', 'Platform dashboard uses robust MariaDB table detection.'],
    [$platformDashboard, 'LEFT JOIN tenant_settings ts', 'Platform dashboard falls back to tenant billing_plan setting.'],
    [$platformDashboard, 'credit_card_fee_cents', 'Platform dashboard includes card fee economics.'],
    [$tenantDashboard, 'SHOW TABLES LIKE', 'Tenant dashboard uses robust MariaDB table detection.'],
    [$tenantDashboard, 'status IS NULL OR status NOT IN', 'Tenant dashboard contact count includes unresolved statuses.'],
    [$salesRepository, 'seller_net_cents', 'Sales repository returns seller net analytics.'],
    [$salesRepository, 'credit_card_fee_cents', 'Sales repository returns credit-card fee analytics.'],
    [$tenantController, 'admin-checkbox-card', 'Tenant billing override is rendered as a clear checkbox card.'],
];

foreach ($checks as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing marker: {$needle} ({$message})\n");
        exit(1);
    }
}

echo "Dashboard schema alignment static checks passed.\n";

// End of file.
