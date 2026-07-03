<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$homePath = $root . '/app/Http/Controllers/Tenant/HomeController.php';
$cssPath = $root . '/public/assets/site.css';

$home = is_file($homePath) ? (file_get_contents($homePath) ?: '') : '';
$css = is_file($cssPath) ? (file_get_contents($cssPath) ?: '') : '';

foreach ([
    'function collapsibleCurationControls',
    'tenant-curation-controls-toggle',
    'tenant-curation-controls-body',
    'Show curation controls',
    'collapsibleCurationControls(',
] as $needle) {
    if (!str_contains($home, $needle)) {
        $failures[] = "HomeController missing {$needle}";
    }
}

if (preg_match('/<details[^>]*tenant-curation-controls-toggle[^>]*\sopen\b/i', $home)) {
    $failures[] = 'Curation controls details element must not start open.';
}

foreach ([
    '.tenant-curation-controls-toggle',
    '.tenant-curation-controls-body',
] as $needle) {
    if (!str_contains($css, $needle)) {
        $failures[] = "site.css missing {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Artwork curation toggle static checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Artwork curation toggle static checks passed.\n";

// End of file.
