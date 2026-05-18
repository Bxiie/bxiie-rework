<?php

declare(strict_types=1);

/**
 * Regression test for database-backed artwork upload pipeline wiring.
 */

$root = dirname(__DIR__, 2);

$files = [
    $root . '/app/Tenant/Artwork/ArtworkUploadService.php',
    $root . '/public/index.php',
    $root . '/database/migrations/0010_artwork_sales_fields.sql',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing artwork DB pipeline file: {$file}\n");
        exit(1);
    }
}

$serviceText = (string) file_get_contents($files[0]);
$indexText = (string) file_get_contents($files[1]);
$migrationText = (string) file_get_contents($files[2]);

foreach (['INSERT INTO media_assets', 'INSERT INTO artworks', 'sale_status', 'price', 'year_created', 'primary_media_id'] as $needle) {
    if (!str_contains($serviceText, $needle)) {
        fwrite(STDERR, "Artwork upload service missing expected fragment: {$needle}\n");
        exit(1);
    }
}

if (!str_contains($indexText, 'new ArtworkUploadService($pdo)')) {
    fwrite(STDERR, "Artwork upload routes must pass PDO to ArtworkUploadService.\n");
    exit(1);
}

foreach (['sale_status', 'price'] as $needle) {
    if (!str_contains($migrationText, $needle)) {
        fwrite(STDERR, "Artwork sales migration missing expected fragment: {$needle}\n");
        exit(1);
    }
}

echo "Artwork DB upload pipeline smoke test passed.\n";

// End of file.
