<?php

declare(strict_types=1);

/**
 * Manual verification script for creating a tenant media asset record.
 */

use App\Platform\Tenancy\TenantResolver;
use App\Platform\Tenancy\TenantStoragePaths;
use App\Support\Database;
use App\Support\Uuid;
use App\Tenant\Media\MediaAssetRepository;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$host = $argv[1] ?? 'bxiie.com';

$pdo = Database::connect($root);
$resolver = new TenantResolver($pdo);
$tenant = $resolver->resolveFromHost($host);

if (!$tenant) {
    fwrite(STDERR, "No tenant resolved for {$host}\n");
    exit(1);
}

$paths = new TenantStoragePaths($tenant);
$repository = new MediaAssetRepository($pdo);

$uuid = Uuid::v4();
$storagePath = $paths->originalsPath('manual-test.jpg');

$id = $repository->create(
    tenant: $tenant,
    uuid: $uuid,
    originalFilename: 'manual-test.jpg',
    storagePath: $storagePath,
    mimeType: 'image/jpeg',
    fileSizeBytes: 12345,
    width: 1200,
    height: 800,
    title: 'Manual Test Image',
    altText: 'Manual test image alt text',
    caption: 'Manual test image caption',
);

echo json_encode([
    'id' => $id,
    'uuid' => $uuid,
    'tenant' => $tenant->slug,
    'storage_path' => $storagePath,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
