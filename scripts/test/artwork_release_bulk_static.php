<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'database/migrations/0072_artwork_release_scheduling.sql' => ['scheduled_publish_at', 'artwork_release_groups', 'artwork_release_group_items', 'artwork.publish_due'],
    'app/Tenant/Artwork/ArtworkPublicationService.php' => ["status = 'published'", "status = 'deployed'", 'scheduled_at <= UTC_TIMESTAMP()', 'TenantDirectoryProfileRepository', 'syncTenant'],
    'app/Http/Controllers/Tenant/Admin/ArtworkReleaseController.php' => ['/admin/artworks/schedule', 'Release groups publish all assigned draft artworks', 'BackgroundJobRepository', 'scheduled_publish_at = NULL'],
    'app/Http/Controllers/Tenant/Admin/ArtworkBulkUploadController.php' => ['webkitdirectory', 'artworks.csv', 'Download sample spreadsheet', 'filename,title,artwork_date', 'ArtworkUploadService', 'image filename is already used by another row', 'use either publish_at or release_group'],
    'app/Http/Controllers/Tenant/Admin/ArtworksController.php' => ['/admin/artwork/bulk', '/admin/release-groups', 'scheduled_publish_at', 'Scheduled ', 'name="release_group_id"', 'name="scheduled_publish_local"', 'Choose one publication method', 'replaceArtworkReleaseGroup', 'WHERE tenant_id = :tenant_id AND id = :id'],
    'app/Http/Routes/tenant.php' => ['/admin/release-groups', '/admin/artwork/bulk/sample.csv', 'ArtworkBulkUploadController', 'ArtworkReleaseController'],
    'scripts/workers/run_once.php' => ["case 'artwork.publish_due':", 'ArtworkPublicationService'],
    'scripts/database/check_migration_integrity.php' => ['0072_artwork_release_scheduling.sql', 'artwork_release_groups', 'scheduled_publish_at'],
    'app/Tenant/Social/SocialRepository.php' => ['publishedPostCountForArtwork', 'social_post_items', 'COUNT(DISTINCT sp.id)'],
    'app/Http/Controllers/Tenant/SocialFrontController.php' => ['Already shared on Instagram.', 'Publishing again will create another post.'],
];
$failures = [];
foreach ($checks as $file => $needles) {
    $contents = is_file($root . '/' . $file) ? (file_get_contents($root . '/' . $file) ?: '') : '';
    foreach ($needles as $needle) if (!str_contains($contents, $needle)) $failures[] = "{$file} missing {$needle}";
}
if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "[PASS] Artwork release, scheduling, bulk upload, and Instagram warning checks passed.\n";
