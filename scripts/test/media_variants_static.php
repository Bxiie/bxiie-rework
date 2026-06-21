<?php

// Verifies media variant schema, generation, upload integration, and controller routing.

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'database/migrations/0038_media_asset_variants.sql' => [
        'CREATE TABLE IF NOT EXISTS media_asset_variants',
        'UNIQUE KEY uq_media_asset_variant (media_asset_id, variant_key)',
        'ON DELETE CASCADE',
    ],
    'app/Tenant/Media/MediaVariantService.php' => [
        "'thumb' => 480",
        "'medium' => 1200",
        "'large' => 2000",
        'imagecopyresampled',
        'upsertVariant',
        '// End of file.',
    ],
    'app/Tenant/Artwork/ArtworkUploadService.php' => [
        'use App\\Tenant\\Media\\MediaVariantService;',
        '$this->createMediaVariants(',
        'new MediaVariantService($this->pdo, dirname(__DIR__, 3))',
    ],
    'app/Http/Controllers/Tenant/MediaController.php' => [
        'private const ALLOWED_VARIANTS',
        '$variantKey = $this->requestedVariant();',
        '$this->findVariant((int) $media[\'id\'], $variantKey)',
        'Cache-Control: public, max-age=31536000, immutable',
        'ETag:',
    ],
    'scripts/maintenance/backfill_media_variants.php' => [
        'Backfills generated variants',
        'ARTSFOLIO_MEDIA_VARIANT_BACKFILL_LIMIT',
        'createForMediaAsset',
    ],
];

foreach ($checks as $relativePath => $needles) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing expected file: {$relativePath}\n");
        exit(1);
    }

    $source = file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            fwrite(STDERR, "Media variants static check failed in {$relativePath}. Missing: {$needle}\n");
            exit(1);
        }
    }
}


$variantService = file_get_contents($root . '/app/Tenant/Media/MediaVariantService.php');
if (str_contains($variantService, 'imagedestroy(')) {
    fwrite(STDERR, "Media variants static check failed: MediaVariantService must not call deprecated imagedestroy() on PHP 8.5.\n");
    exit(1);
}

echo "Media variants static checks passed.\n";

// End of file.
