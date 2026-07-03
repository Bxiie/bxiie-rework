<?php
/**
 * Static checks for the tenant admin artwork edit sales-form guard.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php') ?: '';
foreach ([
    'ArtworkSaleAdminForm render failed',
    'logAdminArtworkEditFailure',
    '[ArtsFolio admin artwork edit]',
    'storage/logs/admin_artwork_edit.log',
    '/tmp/artsfolio_admin_artwork_edit.log',
    'Sales settings could not be loaded',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        $failures[] = "ArtworksController missing {$needle}";
    }
}

if ($failures) {
    fwrite(STDERR, "Admin artwork edit controller guard static checks failed:
 - " . implode("
 - ", $failures) . "
");
    exit(1);
}

echo "Admin artwork edit controller guard static checks passed.
";

// End of file.
