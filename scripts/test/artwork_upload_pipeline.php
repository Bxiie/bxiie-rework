<?php

declare(strict_types=1);

/**
 * Smoke test for artwork upload and legacy migration scaffolding.
 */

$root = dirname(__DIR__, 2);
$required = [
    'app/Tenant/Artwork/ArtworkUploadService.php',
    'app/Http/Controllers/Tenant/Admin/ArtworkUploadController.php',
    'scripts/migration/inventory_legacy_bxiie.php',
    'scripts/migration/stage_legacy_bxiie_images.php',
];

foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) {
        fwrite(STDERR, "Missing artifact: {$file}\n");
        exit(1);
    }
}

$index = file_get_contents($root . '/public/index.php');
if ($index === false || !str_contains($index, '/admin/artwork/upload')) {
    fwrite(STDERR, "Artwork upload route not wired.\n");
    exit(1);
}

echo "Artwork upload pipeline smoke test passed.\n";

// End of file.
