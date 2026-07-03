<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$requiredFiles = [
    'database/migrations/0060_artwork_notes_html.sql',
    'app/Http/Controllers/Tenant/Admin/ArtworksController.php',
    'app/Http/Controllers/Tenant/HomeController.php',
    'public/assets/site.css',
];
foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $failures[] = "Missing {$file}";
    }
}

$migration = file_get_contents($root . '/database/migrations/0060_artwork_notes_html.sql') ?: '';
foreach (['notes_html', 'ALTER TABLE artworks', 'TEXT NULL'] as $needle) {
    if (!str_contains($migration, $needle)) {
        $failures[] = "Migration missing {$needle}";
    }
}

$admin = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php') ?: '';
$adminNeedles = [
    'notes_html',
    'Public notes',
    'Public notes HTML',
    'name="notes_html"',
    'textarea',
];
foreach ($adminNeedles as $needle) {
    if (!str_contains($admin, $needle)) {
        $failures[] = "ArtworksController missing {$needle}";
    }
}

$home = file_get_contents($root . '/app/Http/Controllers/Tenant/HomeController.php') ?: '';
$homeNeedles = [
    'notes_html',
    'artwork-notes',
    'artwork-notes-body',
    <<<'MARKER'
$artwork['notes_html']
MARKER,
];
foreach ($homeNeedles as $needle) {
    if (!str_contains($home, $needle)) {
        $failures[] = "HomeController missing {$needle}";
    }
}

$forbiddenHomeNeedles = [
    <<<'MARKER'
htmlspecialchars((string) ($artwork['notes_html']
MARKER,
    <<<'MARKER'
e((string) ($artwork['notes_html']
MARKER,
];
foreach ($forbiddenHomeNeedles as $needle) {
    if (str_contains($home, $needle)) {
        $failures[] = 'HomeController escapes notes_html, but artwork notes are intentionally trusted tenant-admin HTML.';
    }
}

$css = file_get_contents($root . '/public/assets/site.css') ?: '';
if (!str_contains($css, 'artwork-notes')) {
    $failures[] = 'site.css missing artwork-notes styling marker';
}

if ($failures !== []) {
    fwrite(STDERR, "Artwork notes static checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Artwork notes static checks passed.\n";

// End of file.
