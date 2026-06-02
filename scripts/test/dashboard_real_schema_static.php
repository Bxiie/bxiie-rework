<?php

declare(strict_types=1);

/**
 * Static regression checks for dashboard production schema alignment.
 */

$root = dirname(__DIR__, 2);
$platform = file_get_contents($root . '/app/Http/Controllers/Platform/Admin/DashboardController.php');
$tenant = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/DashboardController.php');

$failures = [];

foreach ([$platform, $tenant] as $source) {
    if ($source === false) {
        $failures[] = 'Unable to read dashboard controller source.';
        continue;
    }
}

if ($platform !== false && str_contains($platform, 'pricing_plans')) {
    $failures[] = 'Platform dashboard must use the production plans table, not pricing_plans.';
}

if ($platform !== false && !str_contains($platform, 'SHOW TABLES LIKE')) {
    $failures[] = 'Platform dashboard should use SHOW TABLES LIKE for production-compatible table checks.';
}

if ($tenant !== false && !str_contains($tenant, 'SHOW TABLES LIKE')) {
    $failures[] = 'Tenant dashboard should use SHOW TABLES LIKE for production-compatible table checks.';
}

if ($platform !== false && !str_contains($platform, 'platformCommissionBasisPoints')) {
    $failures[] = 'Platform dashboard should fall back to platform_sales_commission_basis_points when plans lack a per-plan commission column.';
}

if ($platform !== false && !str_contains($platform, 'Plan dashboard query failed:')) {
    $failures[] = 'Platform dashboard should expose admin-visible plan query failures rather than returning misleading empty data.';
}

if ($tenant !== false && !str_contains($tenant, 'Sales dashboard query failed:')) {
    $failures[] = 'Tenant dashboard should expose admin-visible sales query failures rather than returning misleading empty data.';
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Dashboard production schema static checks passed.\n";

// End of file.
