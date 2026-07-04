<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Tenant/Admin/ArtworksController.php');

$required = [
    'tenant_admin_artworks_return_to',
    'public function __construct',
    '$this->rememberArtworkGridReturnUrl();',
    'rememberArtworkGridReturnUrl',
    'artworkGridReturnUrl',
    'normalizeArtworkGridReturnUrl',
    'HTTP_REFERER',
    '$_POST[\'return_to\']',
    '$_GET[\'return_to\']',
    '$_SESSION[\'tenant_admin_artworks_return_to\']',
    'artworkGridCurrentReturnParam',
    'rawurlencode($current)',
    '$this->artworkGridReturnUrl()',
];

$forbidden = [
    'rememberArtworkGridReturnUrlFromRequestOrReferrer',
    'artworkGridReturnStateScript',
    'sessionStorage.setItem',
];

$missing = [];
$bad = [];

foreach ($required as $needle) {
    if (!str_contains($controller, $needle)) {
        $missing[] = $needle;
    }
}

foreach ($forbidden as $needle) {
    if (str_contains($controller, $needle)) {
        $bad[] = $needle;
    }
}

if ($missing !== [] || $bad !== []) {
    fwrite(STDERR, "[FAIL] Artworks grid return-state static check failed:\n");

    foreach ($missing as $needle) {
        fwrite(STDERR, "[FAIL]  - Missing marker: {$needle}\n");
    }

    foreach ($bad as $needle) {
        fwrite(STDERR, "[FAIL]  - Forbidden marker still present: {$needle}\n");
    }

    exit(1);
}

echo "Artworks grid return-state static checks passed.\n";
