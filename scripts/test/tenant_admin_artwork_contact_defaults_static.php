<?php

/**
 * Static regression checks for tenant action cards, artwork defaults, and contact links.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'dashboard action card class' => ['app/Http/Controllers/Tenant/Admin/DashboardController.php', 'tenant-admin-action-button'],
    'artworks action card group' => ['app/Http/Controllers/Tenant/Admin/ArtworksController.php', 'aria-label="Artwork actions"'],
    'artworks upload action card' => ['app/Http/Controllers/Tenant/Admin/ArtworksController.php', '<strong>Upload artwork</strong>'],
    'artworks placement action card' => ['app/Http/Controllers/Tenant/Admin/ArtworksController.php', '<strong>Artwork placement matrix</strong>'],
    'artworks order action card' => ['app/Http/Controllers/Tenant/Admin/ArtworksController.php', '<strong>Section artwork order</strong>'],
    'new artwork default setting' => ['app/Http/Controllers/Tenant/Admin/SettingsController.php', 'new_artwork_default_status'],
    'new artwork published choice' => ['app/Http/Controllers/Tenant/Admin/SettingsController.php', '>Published immediately</option>'],
    'upload service publication status' => ['app/Tenant/Artwork/ArtworkUploadService.php', ':status'],
    'artwork contact hidden slug' => ['app/Http/Controllers/Tenant/HomeController.php', 'name="artwork_slug"'],
    'server-resolved artwork image URL' => ['app/Http/Controllers/Tenant/ContactController.php', 'Artwork image: '],
    'platform contact topic field' => ['app/Http/Controllers/Platform/MarketingController.php', 'name="topic"'],
];

foreach ($checks as $label => [$relative, $needle]) {
    $contents = file_get_contents($root . '/' . $relative);
    if ($contents === false || !str_contains($contents, $needle)) {
        fwrite(STDERR, "Missing {$label} in {$relative}.\n");
        exit(1);
    }
}

echo "Tenant admin artwork defaults and contact static checks passed.\n";

$artworks = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php');
if ($artworks === false || str_contains($artworks, 'Upload artwork</a> · <a href="/admin/artworks/placement"')) {
    fwrite(STDERR, "Legacy inline artwork action links are still present.\n");
    exit(1);
}

// End of file.
