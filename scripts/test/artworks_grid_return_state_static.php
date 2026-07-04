<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Tenant/Admin/ArtworksController.php');

$required = [
    'tenant_admin_artworks_return_to',
    'rememberArtworkGridReturnUrlFromRequestOrReferrer',
    'artworkGridReturnUrl',
    'normalizeArtworkGridReturnUrl',
    'HTTP_REFERER',
    '$_POST[\'return_to\']',
    'sessionStorage.setItem(key, path + search)',
    'input.name = "return_to"',
    'link.setAttribute("href", stored)',
    'Response::redirect($this->artworkGridReturnUrl())',
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
