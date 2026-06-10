<?php
/**
 * Static regression check for platform pricing economics input sizing.
 */

$css = file_get_contents(__DIR__ . '/../../public/assets/tenant-admin.css');

$required = [
    'ArtsFolio platform pricing economics input width fix',
    'credit_card_percentage',
    'credit_card_fixed_fee',
    'platform_sales_commission_percent',
    'min-width: 9.5rem',
];

foreach ($required as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, "Missing expected pricing input width marker: {$needle}\n");
        exit(1);
    }
}

echo "Platform pricing economics input width CSS is present.\n";

// End of file.
