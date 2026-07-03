<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php';
$salesRepositoryPath = $root . '/app/Tenant/Sales/SalesRepository.php';
$migrationPath = $root . '/database/migrations/0061_artwork_variant_low_stock_tracking.sql';

$controller = file_get_contents($controllerPath) ?: '';
$salesRepository = file_get_contents($salesRepositoryPath) ?: '';
$migration = file_exists($migrationPath) ? (file_get_contents($migrationPath) ?: '') : '';

$failures = [];

if (!str_contains($controller, 'ARTSFOLIO_INTERNAL_NOTES_LABEL_MARKER') || !str_contains($controller, 'Internal notes')) {
    $failures[] = 'Legacy/private notes field must be labelled Internal notes.';
}

$hasAlphaMarker = str_contains($controller, 'ARTSFOLIO_PORTFOLIO_SECTIONS_ALPHA_MARKER');
$hasAlphaOrder = (bool) preg_match('/ORDER\s+BY\s+(LOWER\([^)]*name[^)]*\)|[^;]*name)/i', $controller);
if (!$hasAlphaMarker && !$hasAlphaOrder) {
    $failures[] = 'Portfolio sections should be ordered alphabetically on artwork edit pages.';
}

$hasLowStockMarker = str_contains($salesRepository, 'ARTSFOLIO_LOW_STOCK_TRACKING_MARKER')
    || str_contains($salesRepository, 'low_stock_notification_sent_at')
    || str_contains($migration, 'low_stock_notification_sent_at');
$hasOriginalQuantity = str_contains($salesRepository, 'original_inventory_quantity')
    || str_contains($migration, 'original_inventory_quantity');
if (!$hasLowStockMarker || !$hasOriginalQuantity) {
    $failures[] = 'Multiple-item stock should have low-stock tracking markers.';
}

if ($failures !== []) {
    fwrite(STDERR, "Artwork edit notes/grid/stock static checks failed:
 - " . implode("
 - ", $failures) . "
");
    exit(1);
}

echo "Artwork edit notes/grid/stock static checks passed.
";
