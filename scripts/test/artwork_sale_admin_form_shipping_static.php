<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/app/Tenant/Sales/ArtworkSaleAdminForm.php';
$source = file_get_contents($path);

$failures = [];

if (!str_contains($source, ':shipping_profile_id')) {
    $failures[] = 'ArtworkSaleAdminForm missing shipping_profile_id SQL placeholder.';
}

if (!str_contains($source, "'shipping_profile_id' => \$shippingProfileId > 0 ? \$shippingProfileId : null")) {
    $failures[] = 'ArtworkSaleAdminForm must bind shipping_profile_id when inserting/updating default variants.';
}

if ($failures !== []) {
    fwrite(STDERR, "Artwork sale admin shipping static checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo "Artwork sale admin shipping static checks passed." . PHP_EOL;

// End of file.
