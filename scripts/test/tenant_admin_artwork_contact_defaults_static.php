<?php

/**
 * Static regression checks for tenant action cards, artwork defaults, and contact links.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'dashboard action card class' => ['app/Http/Controllers/Tenant/Admin/DashboardController.php', 'tenant-admin-action-button'],
    'new artwork default setting' => ['app/Http/Controllers/Tenant/Admin/SettingsController.php', 'new_artwork_default_status'],
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

// End of file.
