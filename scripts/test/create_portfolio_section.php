<?php

declare(strict_types=1);

/**
 * Manual verification script for creating a tenant portfolio section and assigning latest artwork.
 */

use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Tenant\Portfolio\PortfolioSectionRepository;

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

$repository = new PortfolioSectionRepository($pdo);
$slug = 'manual-test-section-' . time();

$sectionId = $repository->create(
    tenant: $tenant,
    name: 'Manual Test Section',
    slug: $slug,
    description: 'Manual test section created by create_portfolio_section.php.',
    showAsTab: true,
    sortOrder: 10,
);

$stmt = $pdo->prepare(
    "SELECT id
     FROM artworks
     WHERE tenant_id = :tenant_id
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->execute(['tenant_id' => $tenant->tenantId]);
$artwork = $stmt->fetch();

if ($artwork) {
    $repository->assignArtwork((int) $artwork['id'], $sectionId);
}

echo json_encode([
    'tenant' => $tenant->slug,
    'section_id' => $sectionId,
    'assigned_latest_artwork' => (bool) $artwork,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
