<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php';
$controller = file_get_contents($controllerPath);

$failures = [];

$forbidden = [
    'status <>  ORDER BY',
    "ASC'archived'",
    'final //',
    'ORDER BY ps.name ASC, ps.sort_order ASC\'archived\'',
];

foreach ($forbidden as $fragment) {
    if (strpos($controller, $fragment) !== false) {
        $failures[] = 'ArtworksController contains corrupted SQL/marker fragment: ' . $fragment;
    }
}

$required = [
    "status <> 'archived'",
    'ORDER BY LOWER(name), name, id',
];

foreach ($required as $fragment) {
    if (strpos($controller, $fragment) === false) {
        $failures[] = 'ArtworksController missing expected stable section SQL fragment: ' . $fragment;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "ArtworksController SQL stability static checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo "ArtworksController SQL stability static checks passed.\n";

// End of file.
