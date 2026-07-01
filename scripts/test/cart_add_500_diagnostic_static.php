<?php
/**
 * Static coverage for the cart-add 500 diagnostic helper.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$diagnosticPath = $root . '/scripts/debug/cart_add_500_diagnostic.php';
if (!is_file($diagnosticPath)) {
    $failures[] = 'Missing scripts/debug/cart_add_500_diagnostic.php';
} else {
    $diagnostic = file_get_contents($diagnosticPath) ?: '';
    foreach ([
        'INFORMATION_SCHEMA.COLUMNS',
        'INFORMATION_SCHEMA.TABLES',
        'sales_cart_items.variant_id',
        'sales_cart_items.shipping_price_cents',
        'sales_carts.last_item_added_at',
        'artwork_sale_config.checkout_enabled',
        'artwork_sale_variants.inventory_quantity',
        'available_quantity',
        'no active variant has available_quantity > 0',
        'json_encode($result, JSON_PRETTY_PRINT',
        'End of file.',
    ] as $needle) {
        if (!str_contains($diagnostic, $needle)) {
            $failures[] = "Diagnostic missing {$needle}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Cart add 500 diagnostic static checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Cart add 500 diagnostic static checks passed.\n";

// End of file.
