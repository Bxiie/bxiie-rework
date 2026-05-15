<?php

declare(strict_types=1);

/**
 * Manual verification script for creating a tenant artwork record.
 */

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Artwork\ArtworkRepository;

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

$mediaId = null;

$stmt = $pdo->prepare(
    "SELECT id
     FROM media_assets
     WHERE tenant_id = :tenant_id
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->execute(['tenant_id' => $tenant->tenantId]);
$row = $stmt->fetch();

if ($row) {
    $mediaId = (int) $row['id'];
}

$repository = new ArtworkRepository($pdo);

$id = $repository->create(
    tenant: $tenant,
    title: 'Manual Test Artwork',
    slug: 'manual-test-artwork-' . time(),
    primaryMediaId: $mediaId,
    description: 'Manual test artwork created by create_artwork.php.',
    medium: 'Test media',
    dimensions: '12 x 12 x 12 in',
    yearCreated: '2026',
    status: 'draft',
);

echo json_encode([
    'artwork_id' => $id,
    'tenant' => $tenant->slug,
    'primary_media_id' => $mediaId,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
