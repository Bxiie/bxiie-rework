<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$formPath = $root . '/app/Tenant/Sales/ArtworkSaleAdminForm.php';
$form = file_get_contents($formPath) ?: '';

foreach ([
    '<select name="sale_kind">{$kindOptions}</select>',
    'admin-sale-topline',
    'admin-sale-checkout-toggle',
    'Shipping profile',
    'admin-sale-advanced-shipping',
    'Advanced legacy shipping overrides',
    'shippingProfileOptions',
] as $needle) {
    if (!str_contains($form, $needle)) {
        $failures[] = "ArtworkSaleAdminForm missing {$needle}";
    }
}

foreach ([
    'Legacy one-off',
    'Legacy multiple',
    'name="sales_inventory_mode"',
    '$saleModeOneOffChecked',
    '$saleModeMultipleChecked',
] as $needle) {
    if (str_contains($form, $needle)) {
        $failures[] = "ArtworkSaleAdminForm still contains {$needle}";
    }
}

$css = file_get_contents($root . '/public/assets/tenant-admin.css') ?: '';
foreach ([
    'Compact sales checkout form controls for artwork admin',
    '.admin-sale-checkout-toggle',
    '.admin-sale-advanced-shipping',
] as $needle) {
    if (!str_contains($css, $needle)) {
        $failures[] = "tenant-admin.css missing {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Artwork sales section usability static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Artwork sales section usability static checks passed.\n";

// End of file.
