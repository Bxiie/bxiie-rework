<?php
/**
 * Static coverage for cart-add production diagnostics.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$diag = file_get_contents($root . '/scripts/debug/cart_add_500_diagnostic.php') ?: '';
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php') ?: '';

foreach ([
    'INFORMATION_SCHEMA.COLUMNS',
    'INFORMATION_SCHEMA.TABLES',
    'sales_cart_items.variant_id',
    'artwork_sale_variants.inventory_quantity',
    'available_quantity',
    'tenant_plan_assignments',
    'allow_sales',
] as $needle) {
    if (!str_contains($diag, $needle)) {
        $failures[] = "Diagnostic missing {$needle}";
    }
}

foreach ([
    '[ArtsFolio cart/add]',
    'logCartAddFailure',
    'artwork_id',
    'variant_id',
    'Throwable $e',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        $failures[] = "SalesController missing {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Cart-add runtime logging static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Cart-add runtime logging static checks passed.\n";

// End of file.
