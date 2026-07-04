<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Tenant/Admin/ArtworksController.php');

$required = [
    'tenant_admin_artworks_return_to',
    'rememberArtworkGridReturnUrl',
    'artworkGridReturnUrl',
    'isSafeArtworkGridReturnUrl',
    'parse_url($url, PHP_URL_PATH)',
    'return $path === \'/admin/artworks\';',
];

$missing = [];

foreach ($required as $needle) {
    if (!str_contains($controller, $needle)) {
        $missing[] = $needle;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Artworks grid return-state static check failed:\n");
    foreach ($missing as $needle) {
        fwrite(STDERR, "[FAIL]  - Missing marker: {$needle}\n");
    }
    exit(1);
}

echo "Artworks grid return-state static checks passed.\n";
