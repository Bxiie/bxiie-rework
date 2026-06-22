<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Tenant/Artwork/ArtworkReadRepository.php' => ['publishedPage(', 'LIMIT :limit_count OFFSET :offset_count'],
    'app/Http/Controllers/Tenant/HomeController.php' => ['Pagination::allowedLimitFromQuery(', "'per_page' => \$pageSize", 'aria-label="Portfolio pages"'],
    'app/Http/Controllers/Tenant/Admin/ArtworksController.php' => ['Pagination::allowedLimitFromQuery(', 'Artworks per page', "'per_page' => \$pageSize", 'OFFSET :offset_count'],
    'app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php' => ['Pagination::allowedLimitFromQuery(', 'Artworks per page', "Save placements for this page", "visible_artwork_ids[]", "preserves assignments on every other page"],
    'database/migrations/0041_artwork_catalog_indexes.sql' => ['idx_artworks_tenant_sale_status_id', 'idx_artwork_section_assignments_section_sort_artwork'],
];
foreach ($checks as $relative => $needles) {
    $text = file_get_contents($root . '/' . $relative);
    if ($text === false) {
        throw new RuntimeException("Missing {$relative}");
    }
    foreach ($needles as $needle) {
        if (!str_contains($text, $needle)) {
            throw new RuntimeException("Missing {$needle} in {$relative}");
        }
    }
}
echo "Phase 5 artwork catalog static checks passed.\n";

// End of file.
