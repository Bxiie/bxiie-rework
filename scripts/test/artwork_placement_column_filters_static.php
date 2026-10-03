<?php

declare(strict_types=1);

/**
 * Static regression checks for the artwork placement matrix's column
 * controls: a client-side column-visibility search (legitimately local to
 * the current page, since it only hides/shows already-rendered columns) and
 * a server-side Home/section row filter (?filter=, rendered as a toggle link
 * per column header). The row filter was previously client-side-only, which
 * silently limited "filter by Home" to whatever rows happened to be on the
 * currently loaded page — see artwork_placement_home_filter_static.php for
 * the functional regression test proving it now searches the whole catalog.
 */

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
    'data-placement-column-name=',
    "if (\$filter === 'home') {",
    'private function normalizedAssignmentFilter(string $raw, array $sections): string',
    'private function filterToggleHref(string $path, array $base, string $filterValue, bool $active): string',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "Artwork placement controller missing: {$needle}\n");
        exit(1);
    }
}

// The obsolete client-side-only row filter (and its now-unnecessary separate
// reset control) must not come back — that's exactly what made "filter by
// Home" show an arbitrary subset instead of the real, whole-catalog result.
foreach ([
    'data-placement-assignment-filter',
    'data-placement-assignment-reset',
    'data-placement-assignment=',
] as $needle) {
    if (str_contains($controller, $needle)) {
        fwrite(STDERR, "Artwork placement controller still contains the obsolete client-side row filter marker: {$needle}\n");
        exit(1);
    }
}

$script = file_get_contents($scriptFile);
foreach ([
    'applyPlacementFilters',
    'placementColumnQuery',
    '[data-placement-column-search]',
    '[data-placement-column-reset]',
] as $needle) {
    if (!str_contains($script, $needle)) {
        fwrite(STDERR, "Artwork pagination script missing placement column-search support: {$needle}\n");
        exit(1);
    }
}
foreach (['placementAssignmentFilter', '[data-placement-assignment-filter]', '[data-placement-assignment-reset]'] as $needle) {
    if (str_contains($script, $needle)) {
        fwrite(STDERR, "Artwork pagination script still contains the obsolete client-side row filter: {$needle}\n");
        exit(1);
    }
}

if (!str_contains($script, 'applyPlacementFilters();') || !str_contains($script, 'root.replaceWith(replacement)')) {
    fwrite(STDERR, "Column-visibility search is not reapplied after AJAX replacement.\n");
    exit(1);
}

echo "Artwork placement column-filter static checks passed.\n";

// End of file.
