<?php

declare(strict_types=1);

/**
 * Static regression checks for shopping cart phase 4 checkout behavior.
 */

$root = dirname(__DIR__, 2);
$failures = [];

$required = [
    'app/Tenant/Sales/StripeCheckoutService.php' => ['shipping_options[0][shipping_rate_data]', 'fixed_amount', 'customer_email', 'variantDescription', 'product_data][description'],
    'app/Tenant/Sales/SalesRepository.php' => ['shipping_cents', 'sales_order_items', 'variant_label_snapshot', 'sales_inventory_reservations', 'artwork_sale_variants', 'syncLegacyArtworkInventoryFromVariants', 'Reserved variant inventory could not be consumed'],
    'app/Http/Controllers/Tenant/SalesController.php' => ['shipping_cents', 'customer_email', 'Checkout with Stripe', 'seller_net_cents'],
    'database/migrations/0056_cart_variant_checkout_shipping.sql' => ['uq_sales_inventory_reservation_order_variant', 'MODIFY COLUMN variant_id BIGINT UNSIGNED NOT NULL', 'idx_sales_inventory_reservation_order'],
    'docs/dev/sales-cart-checkout.md' => ['Phase 4', 'inline shipping'],
    'docs/admin/sales-cart-products.md' => ['Phase 4 checkout'],
    'docs/user/buying-artwork.md' => ['Stripe Checkout'],
    'PROJECT_STATE.md' => ['Shopping cart phase 4'],
];

foreach ($required as $relative => $needles) {
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
    fwrite(STDERR, "Shopping cart phase 4 static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Shopping cart phase 4 static checks passed.\n";

// End of file.
