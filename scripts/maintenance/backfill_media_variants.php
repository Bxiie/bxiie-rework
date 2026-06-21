<?php

// Backfills generated variants for existing media assets after 0038_media_asset_variants.sql.

declare(strict_types=1);

use App\Support\Database;
use App\Tenant\Media\MediaVariantService;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$limit = max(1, (int) (getenv('ARTSFOLIO_MEDIA_VARIANT_BACKFILL_LIMIT') ?: 500));
$service = new MediaVariantService($pdo, $root);

if (!tableExists($pdo, 'media_asset_variants')) {
    fwrite(STDERR, "Missing media_asset_variants table. Run php scripts/database/migrate.php first.\n");
    exit(1);
}

$stmt = $pdo->prepare(
    "SELECT m.id, m.storage_path, m.mime_type, m.width, m.height, m.file_size_bytes
       FROM media_assets m
      WHERE NOT EXISTS (
            SELECT 1
              FROM media_asset_variants v
             WHERE v.media_asset_id = m.id
               AND v.variant_key = 'thumb'
      )
      ORDER BY m.id ASC
      LIMIT :limit_count"
);
$stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
$stmt->execute();

$processed = 0;
$missing = 0;
$failed = 0;

foreach ($stmt->fetchAll() as $media) {
    $absolute = $root . '/' . ltrim((string) $media['storage_path'], '/');
    if (!is_file($absolute)) {
        $missing++;
        continue;
    }

    try {
        $service->createForMediaAsset(
            mediaAssetId: (int) $media['id'],
            sourceRelativePath: (string) $media['storage_path'],
            mimeType: (string) ($media['mime_type'] ?: 'application/octet-stream'),
            sourceWidth: $media['width'] !== null ? (int) $media['width'] : null,
            sourceHeight: $media['height'] !== null ? (int) $media['height'] : null,
            sourceBytes: $media['file_size_bytes'] !== null ? (int) $media['file_size_bytes'] : null,
        );
        $processed++;
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "Media {$media['id']} failed: {$e->getMessage()}\n");
    }
}

echo json_encode([
    'processed' => $processed,
    'missing_files' => $missing,
    'failed' => $failed,
    'limit' => $limit,
], JSON_PRETTY_PRINT) . PHP_EOL;

exit($failed === 0 ? 0 : 1);

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
    $stmt->execute(['table_name' => $table]);

    return (bool) $stmt->fetchColumn();
}

// End of file.
