<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Tenant/Admin/ArtworksController.php');
$failures = [];

foreach ([
    'rawurlencode return parameter marker' => '$returnToParam = rawurlencode($returnTo);',
    'encoded thumbnail edit return_to marker' => 'href="/admin/artworks/edit?id={$artworkId}&return_to={$returnToParam}"',
    'safe edit return_to normalization marker' => '$returnTo = $this->safeArtworkReturnTo((string) ($_GET[\'return_to\'] ?? $this->artworkGridReturnUrl()));',
    'safe save return_to redirect marker' => '$this->artworkReturnWithNotice($returnTo, \'artwork-saved\') . \'#artwork-\' . $id',
] as $label => $needle) {
    if (!str_contains($source, $needle)) {
        $failures[] = $label;
    }
}

if (preg_match('/href="\/admin\/artworks\/edit[^\"]*return_to=\{\$returnToValue\}/', $source)) {
    $failures[] = 'edit href still uses unencoded returnToValue';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Artwork grid return_to encoding static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "[PASS] Artwork grid return_to encoding static check passed.\n");

// End of file.
