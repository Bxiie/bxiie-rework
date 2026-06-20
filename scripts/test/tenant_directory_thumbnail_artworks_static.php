<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$artworksController = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php');
$settingsController = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php');
$publicIndex = file_get_contents($root . '/public/index.php');
$preflight = file_get_contents($root . '/scripts/test/preflight.sh');

$failures = [];

$checks = [
    [$artworksController, "name=\"directory_thumbnail\"", 'Artwork list directory thumbnail checkbox'],
    [$artworksController, '/admin/artworks/directory-thumbnail', 'Artwork directory thumbnail form action'],
    [$artworksController, 'updateDirectoryThumbnail', 'Artwork directory thumbnail update handler'],
    [$artworksController, 'platform_directory_thumbnail_artwork_id', 'Tenant setting persistence key in artworks controller'],
    [$artworksController, 'isValidDirectoryThumbnailArtwork', 'Published artwork with media validation'],
    [$publicIndex, "/admin/artworks/directory-thumbnail", 'Directory thumbnail route'],
    [$settingsController, 'Set the directory thumbnail from the Artworks page', 'Directory page points thumbnail selection to artworks page'],
    [$settingsController, 'Manage artworks and choose directory thumbnail', 'Directory page artwork link'],
    [$preflight, 'tenant_directory_thumbnail_artworks_static.php', 'Preflight wiring'],
];

foreach ($checks as [$haystack, $needle, $label]) {
    if (!str_contains($haystack, $needle)) {
        $failures[] = $label . ' missing: ' . $needle;
    }
}

if (str_contains($settingsController, 'select name="platform_directory_thumbnail_artwork_id"')) {
    $failures[] = 'Directory thumbnail dropdown should be removed from the Directory settings subpage.';
}

$directoryKeysBlock = "'directory' => [\n                'platform_directory_opt_in', 'platform_directory_summary',\n            ],";
if (!str_contains($settingsController, $directoryKeysBlock)) {
    $failures[] = 'Directory settings save keys should not include platform_directory_thumbnail_artwork_id.';
}

if ($failures !== []) {
    fwrite(STDERR, "Tenant artwork directory thumbnail static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Tenant artwork directory thumbnail static checks passed.\n";

// End of file.
