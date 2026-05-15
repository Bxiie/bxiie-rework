<?php

declare(strict_types=1);

/**
 * Manual verification script for storing tenant media content and creating its DB row.
 */

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Support\Storage\LocalStorageProvider;
use App\Tenant\Media\MediaAssetRepository;
use App\Tenant\Media\MediaAssetService;

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

$storageRoot = $root . '/storage/uploads';
$storage = new LocalStorageProvider($storageRoot);
$repository = new MediaAssetRepository($pdo);
$service = new MediaAssetService($storage, $repository);

$id = $service->storeOriginal(
    tenant: $tenant,
    originalFilename: 'service-test.txt',
    contents: 'ArtsFolio tenant media storage test.',
    mimeType: 'text/plain',
    title: 'Service Test File',
    altText: 'Service test file',
    caption: 'Created by store_media_asset.php',
);

echo "Stored media asset ID: {$id}\n";

// End of file.
