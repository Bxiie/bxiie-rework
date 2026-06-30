<?php
/**
 * Static checks for public shopping-cart product availability repair.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Http/Controllers/Tenant/HomeController.php' => [
        'saleConfigForPublicArtwork',
        'saleVariantsForPublicArtwork',
        'cartForm(TenantContext $tenant, array $artwork, ?array $config = null, array $variants = [])',
        'Online checkout is not enabled for this item yet.',
        'SELECT COALESCE(p.allow_sales, 0) AS allow_sales',
        'ORDER BY tpa.id DESC',
        'if ($available <= 0 && ((int) ($artwork[\'inventory_quantity\'] ?? 0)) > 0)',
    ],
    'app/Http/Controllers/Tenant/SalesController.php' => [
        'SELECT COALESCE(p.allow_sales, 0) AS allow_sales',
        'ORDER BY tpa.id DESC',
        'return $row && (int) ($row[\'allow_sales\'] ?? 0) === 1;',
    ],
    'database/migrations/0058_repair_cart_product_public_availability.sql' => [
        'INSERT INTO artwork_sale_variants',
        'UPDATE artwork_sale_config c',
        'UPDATE artwork_sale_variants v',
        'NOT EXISTS',
        'End of file.',
    ],
];

$failures = [];
foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $failures[] = "Missing {$relative}";
        continue;
    }
    $contents = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $failures[] = "Missing {$needle} in {$relative}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Public product cart static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Public product cart static checks passed.\n";

// End of file.
