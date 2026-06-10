<?php

declare(strict_types=1);

/**
 * Static regression checks for artwork placement tools and tenant reset scope.
 */

$root = dirname(__DIR__, 2);
$checks = [
    'placement matrix route' => [$root . '/public/index.php', "/admin/artworks/placement"],
    'section order route' => [$root . '/public/index.php', "/admin/portfolio-sections/order"],
    'tenant reset guard helper' => [$root . '/public/index.php', 'tenantPasswordResetRecipientExists'],
    'tenant reset membership scope' => [$root . '/public/index.php', 'tenant_memberships tm'],
    'tenant reset legacy tenant user scope' => [$root . '/public/index.php', 'tenant_users tu'],
    'placement controller thumbnail column' => [$root . '/app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php', 'Thumbnail'],
    'placement controller home page column' => [$root . '/app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php', 'Home page'],
    'drag ordering asset' => [$root . '/public/assets/admin/artwork-placement-order.js', 'dragover'],
];

$missing = [];
foreach ($checks as $label => [$file, $needle]) {
    $source = is_file($file) ? (string) file_get_contents($file) : '';
    if ($source === '' || !str_contains($source, $needle)) {
        $missing[] = $label;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Missing artwork placement/password reset marker(s): " . implode(', ', $missing) . "\n");
    exit(1);
}

echo "Artwork placement and tenant password reset static checks passed.\n";

// End of file.
