<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'public/assets/artwork-pagination.js',
    'app/Http/Controllers/Tenant/HomeController.php',
    'app/Http/Controllers/Tenant/Admin/ArtworksController.php',
    'app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php',
];

foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) {
        fwrite(STDERR, "Missing required file: {$file}\n");
        exit(1);
    }
}

$script = file_get_contents($root . '/public/assets/artwork-pagination.js');
$requirements = [
    'data-artwork-pager-root',
    'fetch(url',
    'DOMParser',
    'history.pushState',
    "window.addEventListener('popstate'",
    'form.requestSubmit()',
    "window.location.assign(url)",
];
foreach ($requirements as $needle) {
    if (!str_contains($script, $needle)) {
        fwrite(STDERR, "Artwork pagination script missing: {$needle}\n");
        exit(1);
    }
}

foreach (array_slice($files, 1) as $file) {
    $source = file_get_contents($root . '/' . $file);
    foreach (['data-artwork-pager-root', 'data-artwork-page-link', '/assets/artwork-pagination.js'] as $needle) {
        if (!str_contains($source, $needle)) {
            fwrite(STDERR, "{$file} missing AJAX pagination marker: {$needle}\n");
            exit(1);
        }
    }
    if (!str_contains($source, '‹ Previous') || !str_contains($source, 'Next ›')) {
        fwrite(STDERR, "{$file} missing Previous/Next pager labels.\n");
        exit(1);
    }
    if (str_contains($source, '− Previous') || str_contains($source, 'Next +')) {
        fwrite(STDERR, "{$file} still contains obsolete pager labels.\n");
        exit(1);
    }
}

foreach (['app/Http/Controllers/Tenant/HomeController.php', 'app/Http/Controllers/Tenant/Admin/ArtworksController.php', 'app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php'] as $file) {
    $source = file_get_contents($root . '/' . $file);
    if (!str_contains($source, 'data-artwork-page-form')) {
        fwrite(STDERR, "{$file} missing enhanced GET form marker.\n");
        exit(1);
    }
}

echo "Artwork AJAX pagination static checks passed.\n";
