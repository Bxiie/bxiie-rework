<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$required = [
    'app/Tenant/Sales/SalesRepository.php' => [
        'tenantSalesSummary',
        'tenantSalesByDay',
        'tenantBestSellers',
        'platformSalesSummary',
        'platformSalesByDay',
        'platformSalesByTenant',
    ],
    'app/Http/Controllers/Tenant/Admin/SalesAnalyticsController.php' => [
        'Sales analytics',
        'Best sellers',
        'Sales by day',
    ],
    'app/Http/Controllers/Platform/Admin/SalesAnalyticsController.php' => [
        'Sales by tenant',
        'Platform sales by day',
        'Sales analytics',
    ],
    'public/index.php' => [
        '/admin/sales/analytics',
        '/platform/admin/sales/analytics',
        'SalesAnalyticsController',
    ],
];

$missing = [];
foreach ($required as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        $missing[] = $file . ' missing';
        continue;
    }
    $contents = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $missing[] = $file . ' missing marker: ' . $needle;
        }
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Sales phase 3 static checks failed:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}

echo "Sales phase 3 static checks passed.\n";

// End of file.
