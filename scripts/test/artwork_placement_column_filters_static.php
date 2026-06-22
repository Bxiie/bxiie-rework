<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerFile = $root . '/app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php';
$scriptFile = $root . '/public/assets/artwork-pagination.js';

foreach ([$controllerFile, $scriptFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing required file: {$file}\n");
        exit(1);
    }
}

$controller = file_get_contents($controllerFile);
foreach ([
    'data-placement-matrix',
    'data-placement-column-search',
    'data-placement-column-reset',
    'data-placement-assignment-reset',
    'data-placement-assignment-filter="home"',
    'data-placement-assignment-filter="section-',
    'data-placement-column-name=',
    'data-placement-assignment="section-',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "Artwork placement controller missing: {$needle}\n");
        exit(1);
    }
}

$script = file_get_contents($scriptFile);
foreach ([
    'applyPlacementFilters',
    'placementColumnQuery',
    'placementAssignmentFilter',
    '[data-placement-column-search]',
    '[data-placement-assignment-filter]',
    '[data-placement-column-reset]',
    '[data-placement-assignment-reset]',
    'CSS.escape',
] as $needle) {
    if (!str_contains($script, $needle)) {
        fwrite(STDERR, "Artwork pagination script missing placement filter support: {$needle}\n");
        exit(1);
    }
}

if (!str_contains($script, 'applyPlacementFilters();') || !str_contains($script, 'root.replaceWith(replacement)')) {
    fwrite(STDERR, "Placement filters are not reapplied after AJAX replacement.\n");
    exit(1);
}

echo "Artwork placement column-filter static checks passed.\n";
