<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$checks = [
    'database/migrations/0059_tenant_shipping_profiles.sql' => [
        'tenant_shipping_profiles',
        'Small flat items',
        'flat_profile',
        'shipping_profile_id',
        'idx_sales_cart_items_shipping_profile',
    ],
    'app/Tenant/Sales/ShippingProfileService.php' => [
        'final class ShippingProfileService',
        'small_flat',
        'large_quote',
        'ensureDefaultProfiles',
    ],
    'app/Tenant/Sales/ArtworkSaleAdminForm.php' => [
        'shipping_profile_id',
        'Shipping profile',
        'ShippingProfileService',
    ],
    'app/Http/Controllers/Tenant/SalesController.php' => [
        'ShippingProfileService',
        'shipping_profile_max_cents',
        'requires a shipping quote',
    ],
    'app/Tenant/Sales/SalesRepository.php' => [
        'shippingAllocations',
        'profileShippingTotal',
        'shipping_profile_id',
        'ten different sticker products',
    ],
    'docs/admin/sales-cart-products.md' => [
        'Shipping profiles',
        'Small flat items',
    ],
    'docs/user/buying-artwork.md' => [
        'shared shipping profile',
    ],
    'PROJECT_STATE.md' => [
        'tenant_shipping_profiles',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        $failures[] = "Missing {$file}";
        continue;
    }
    $text = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($text, $needle)) {
            $failures[] = "{$file} missing {$needle}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Shipping profile static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Shipping profile static checks passed.\n";

// End of file.
