<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php';
$salesRepositoryPath = $root . '/app/Tenant/Sales/SalesRepository.php';
$migrationPath = $root . '/database/migrations/0061_artwork_variant_low_stock_tracking.sql';

$controller = file_get_contents($controllerPath);
$salesRepository = file_exists($salesRepositoryPath) ? file_get_contents($salesRepositoryPath) : '';
$migration = file_exists($migrationPath) ? file_get_contents($migrationPath) : '';
$failures = [];

function fieldLabelBlock(string $source, string $fieldName): ?string
{
    $needle = 'name="' . $fieldName . '"';
    $pos = strpos($source, $needle);
    if ($pos === false) {
        return null;
    }

    $before = substr($source, 0, $pos);
    $labelStart = strrpos($before, '<label');
    $labelEnd = strpos($source, '</label>', $pos);

    if ($labelStart === false || $labelEnd === false) {
        return null;
    }

    return substr($source, $labelStart, $labelEnd + strlen('</label>') - $labelStart);
}

$internalBlock = fieldLabelBlock($controller, 'notes');
$publicBlock = fieldLabelBlock($controller, 'notes_html');

if ($internalBlock === null) {
    $failures[] = 'Legacy/private notes field must still use name="notes" inside a label.';
} else {
    if (strpos($internalBlock, 'Internal notes') === false) {
        $failures[] = 'Legacy/private notes field must be labelled Internal notes.';
    }
    if (strpos($internalBlock, 'Public notes HTML') !== false) {
        $failures[] = 'Legacy/private notes field must not be labelled as Public notes HTML.';
    }
}

if ($publicBlock === null) {
    $failures[] = 'Public notes field must use name="notes_html" inside a label.';
} else {
    if (strpos($publicBlock, 'Public notes HTML') === false) {
        $failures[] = 'Public notes field must be labelled Public notes HTML.';
    }
}

if (strpos($controller, 'status <>  ORDER BY') !== false || strpos($controller, "ASC'archived'") !== false || strpos($controller, 'final //') !== false) {
    $failures[] = 'ArtworksController contains corrupted marker or SQL fragments.';
}

$hasAlphabeticSections = strpos($controller, 'ORDER BY LOWER(name), name, id') !== false
    || strpos($controller, 'ORDER BY ps.name ASC, ps.sort_order ASC') !== false;
if (!$hasAlphabeticSections) {
    $failures[] = 'Portfolio sections should be ordered alphabetically on artwork edit pages.';
}

$hasThumbnailEditLink = (
    strpos($controller, 'ARTWORK_GRID_THUMBNAILS_LINK_TO_EDIT_WITH_RETURN_TO') !== false
    || (strpos($controller, '/admin/artworks/edit?id=') !== false && strpos($controller, 'return_to') !== false)
);
if (!$hasThumbnailEditLink) {
    $failures[] = 'Artwork grid thumbnails should link to edit pages and preserve return_to.';
}

$hasLowStockTracking = (
    strpos($salesRepository, 'ARTSFOLIO_LOW_STOCK_TRACKING_MARKER') !== false
    || strpos($salesRepository, 'LOW_STOCK') !== false
    || strpos($salesRepository, 'low_stock') !== false
    || (strpos($migration, 'original_inventory_quantity') !== false && strpos($migration, 'low_stock_notification_sent_at') !== false)
);
if (!$hasLowStockTracking) {
    $failures[] = 'Multiple-item stock should have low-stock tracking markers.';
}

if ($failures !== []) {
    fwrite(STDERR, "Artwork edit notes/grid/stock static checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo "Artwork edit notes/grid/stock static checks passed.\n";
