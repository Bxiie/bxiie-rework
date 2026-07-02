<?php
/**
 * Static checks for shipping-profile admin edit fallback behavior.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$formPath = $root . '/app/Tenant/Sales/ArtworkSaleAdminForm.php';
if (!is_file($formPath)) {
    $failures[] = 'Missing app/Tenant/Sales/ArtworkSaleAdminForm.php';
} else {
    $form = file_get_contents($formPath) ?: '';
    foreach ([
        'private function shippingProfileOptions',
        'tenant_shipping_profiles table is missing',
        'ShippingProfileService class is missing',
        'Unable to load shipping profiles',
        'private function tableExists',
        'INFORMATION_SCHEMA.TABLES',
        'Shipping profiles unavailable until migration 0059 runs',
        '[ArtsFolio admin artwork edit]',
    ] as $needle) {
        if (!str_contains($form, $needle)) {
            $failures[] = "ArtworkSaleAdminForm missing {$needle}";
        }
    }
}

if ($failures) {
    fwrite(STDERR, "Shipping profile admin edit static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Shipping profile admin edit static checks passed.\n";

// End of file.
