<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Tenant/Media/MediaRotationService.php' => ['rotateArtworkPrimary', "['left', 'right']", 'imagerotate', 'JOIN media_assets m ON m.id = a.primary_media_id AND m.tenant_id = a.tenant_id', 'INSERT INTO media_assets', 'MediaVariantService', 'UPDATE artworks SET primary_media_id = :media_id'],
    'app/Http/Controllers/Tenant/Admin/ArtworksController.php' => ['action="/admin/artworks/rotate"', 'Rotate left 90°', 'Rotate right 90°', 'public function rotateImage', 'validate((string) ($_POST[\'csrf_token\']', 'tenant.artwork.image_rotated', 'syncTenant'],
    'app/Http/Routes/tenant.php' => ["post('/admin/artworks/rotate'", '->rotateImage('],
    'scripts/test/fixtures/route_inventory.json' => ['"path": "/admin/artworks/rotate"'],
];

$failures = [];
foreach ($checks as $relative => $needles) {
    $source = is_file($root . '/' . $relative) ? (file_get_contents($root . '/' . $relative) ?: '') : '';
    foreach ($needles as $needle) if (!str_contains($source, $needle)) $failures[] = $relative . ' missing ' . $needle;
}
if ($failures !== []) {
    fwrite(STDERR, "Artwork image rotation checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "Artwork image rotation static checks passed.\n";

// End of file.
