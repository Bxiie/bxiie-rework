<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php');

$failures = [];

foreach ([
    'ArtworkSaleAdminForm save failed',
    'logAdminArtworkEditFailure($tenant->tenantId, $id',
    'Artwork sales settings could not be saved',
] as $fragment) {
    if (!str_contains($source, $fragment)) {
        $failures[] = 'ArtworksController missing sale-save logging marker: ' . $fragment;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Artwork sale save logging static checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo "Artwork sale save logging static checks passed." . PHP_EOL;

// End of file.
