<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$form = file_get_contents($root . '/app/Tenant/Sales/ArtworkSaleAdminForm.php') ?: '';

foreach ([
    '<details class="admin-sale-variants">',
    'Variant rows for sizes, fits, editions, and inventory',
    'admin-sale-variant-table',
] as $needle) {
    if (!str_contains($form, $needle)) {
        $failures[] = "ArtworkSaleAdminForm missing {$needle}";
    }
}

foreach ([
    '<details class="admin-sale-variants" open>',
    '<details open class="admin-sale-variants">',
] as $needle) {
    if (str_contains($form, $needle)) {
        $failures[] = "Variant rows section is still open by default via {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Artwork variant rows collapsed static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Artwork variant rows collapsed static checks passed.\n";

// End of file.
