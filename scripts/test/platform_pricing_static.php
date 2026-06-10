<?php

/**
 * Static regression checks for platform pricing, commission, and stats drill-down wiring.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Http/Controllers/Platform/Admin/PricingController.php' => [
        'platform_sales_commission_basis_points',
        'allowed_artworks',
        'allowed_email_addresses',
        'platform.pricing.updated',
    ],
    'app/Http/Controllers/Platform/PricingController.php' => [
        'Sales commission',
        'Includes ArtsFolio notification/link on free tenant pages',
        'allowed_email_addresses',
    ],
    'app/Http/Controllers/Platform/Admin/StatsController.php' => [
        'ipRowsForLocation',
        'Unique IP detail for',
        'AND (country <=> :country)',
    ],
    'app/Http/Controllers/Platform/Admin/TenantsController.php' => [
        "record('platform.tenant.status_changed', $" . 'tenantId',
    ],
    'app/Http/Controllers/Tenant/HomeController.php' => [
        'artsfolioFreePlanLink',
        'Created with ArtsFolio',
    ],
    'database/migrations/0023_pricing_limits_commission.sql' => [
        'allowed_artworks',
        'platform_sales_commission_basis_points',
        'End of file.',
    ],
];

foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$relative}\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            fwrite(STDERR, "Missing marker {$needle} in {$relative}\n");
            exit(1);
        }
    }
}

echo "Platform pricing/static checks passed.\n";

// End of file.
