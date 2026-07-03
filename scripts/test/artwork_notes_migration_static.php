<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$migrationPath = $root . '/database/migrations/0060_artwork_notes_html.sql';
$statePath = $root . '/PROJECT_STATE.md';

$failures = [];

if (!is_file($migrationPath)) {
    $failures[] = '0060 artwork notes migration file is missing.';
} else {
    $migration = file_get_contents($migrationPath) ?: '';

    $requiredMarkers = [
        'ALTER TABLE artworks' => '0060 artwork notes migration should alter artworks.',
        'notes_html' => '0060 artwork notes migration should add notes_html.',
        'TEXT' => '0060 artwork notes migration should store multiline notes in a TEXT-compatible column.',
    ];

    foreach ($requiredMarkers as $marker => $message) {
        if (stripos($migration, $marker) === false) {
            $failures[] = $message;
        }
    }

    if (stripos($migration, 'ADD COLUMN') === false && stripos($migration, 'ADD notes_html') === false) {
        $failures[] = '0060 artwork notes migration should add the notes_html column.';
    }

    // Do not assert placement such as "AFTER description" here. This migration may
    // already be applied locally or in production, and changing cosmetic placement
    // text after application causes checksum drift without changing behavior.
}

if (!is_file($statePath)) {
    $failures[] = 'PROJECT_STATE.md is missing.';
} else {
    $state = file_get_contents($statePath) ?: '';
    foreach (['artwork notes', 'notes_html'] as $marker) {
        if (stripos($state, $marker) === false) {
            $failures[] = "PROJECT_STATE.md missing artwork notes marker: {$marker}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Artwork notes migration static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Artwork notes migration static checks passed.\n";

// EOF
