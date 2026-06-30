<?php

declare(strict_types=1);

/**
 * Static regression checks for shopping cart phase 3 public buyer behavior.
 * Includes the public cart chrome helper because product-page repairs must
 * not accidentally remove the non-empty cart navigation marker.
 */

$root = dirname(__DIR__, 2);
$failures = [];

$required = [
    'app/Tenant/Sales/CartIdentityService.php' => ['sales_cart_aliases', 'createBridgeToken', 'consumeBridgeToken', 'hash_hmac', 'bridgePixels'],
    'app/Http/Controllers/Tenant/SalesController.php' => ['variant_id', 'addVariantItem', 'bridgePixel', 'bridge(', 'shippingForVariant'],
    'app/Tenant/Sales/SalesRepository.php' => ['saleConfigForArtwork', 'variantsForArtwork', 'variantForPurchase', 'addVariantItem', 'cartSummary'],
    'app/Http/Controllers/Tenant/HomeController.php' => ['CartIdentityService', 'cartChrome', 'saleVariantsForPublicArtwork', 'name="variant_id"', 'site-cart-link'],
    'app/Http/Routes/tenant.php' => ['/cart/bridge-pixel', '/cart/bridge'],
    'database/migrations/0055_cart_variant_public_runtime.sql' => ['idx_sales_cart_items_cart', 'uq_sales_cart_items_variant', 'MODIFY COLUMN variant_id BIGINT UNSIGNED NOT NULL'],
    'public/assets/site.css' => ['.site-cart-link', '.artwork-sale-options', '.cart-review-table'],
    'docs/dev/sales-cart-checkout.md' => ['Phase 3', 'cart bridge'],
    'docs/admin/sales-cart-products.md' => ['variant-aware public cart'],
    'docs/user/buying-artwork.md' => ['choose a size'],
    'PROJECT_STATE.md' => ['Shopping cart phase 3'],
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
    fwrite(STDERR, "Shopping cart phase 3 static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Shopping cart phase 3 static checks passed.\n";

// End of file.
