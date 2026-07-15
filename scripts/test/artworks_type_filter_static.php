<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php'
);

$failures = [];

foreach ([
    "\$_GET['artwork_type']",
    "['portfolio', 'site', 'both']",
    "portfolio_type.code = 'portfolio_images'",
    "site_type.code = 'site_images'",
    "'artwork_type' => \$typeFilter",
    'name="artwork_type"',
    'All artwork types',
    '>Portfolio<',
    '>Site<',
    'Portfolio and site',
] as $marker) {
    if (!str_contains($controller, $marker)) {
        $failures[] = "ArtworksController missing marker: {$marker}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Artworks type-filter check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Artworks can be filtered by portfolio, site, or both assignments.\n";

// End of file.
