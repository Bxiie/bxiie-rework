#!/usr/bin/php
<?php

/**
 * Prevent artist-specific demo copy from becoming a new tenant default.
 */

declare(strict_types=1);

error_reporting(E_ALL);

set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
);

$root = dirname(__DIR__, 2);

/*
 * Build the prohibited sentence from fragments so this regression test does
 * not contain the complete literal and therefore cannot match itself.
 */
$phrase = implode('', [
    'Contemporary mixed-media work, archival textures, ',
    'fragments, signals, and beautiful static from the ',
    'machine room of memory.',
]);

$scanRoots = [
    $root . '/app',
    $root . '/config',
    $root . '/template',
    $root . '/scripts',
    $root . '/database/seeders',
    $root . '/database/seeds',
];

$allowedExtensions = [
    'php', 'sql', 'txt', 'md', 'json', 'yaml', 'yml',
    'ini', 'conf', 'sh', 'py', 'html',
];

$problems = [];

foreach ($scanRoots as $scanRoot) {
    if (!is_dir($scanRoot)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $scanRoot,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        $relative = str_replace($root . '/', '', $path);

        if (
            str_contains($relative, '/vendor/')
            || str_contains($relative, '/node_modules/')
            || str_contains($relative, '/.update-backups/')
            || str_starts_with($file->getFilename(), '._')
        ) {
            continue;
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $body = file_get_contents($path);
        if ($body !== false && str_contains($body, $phrase)) {
            $problems[] = $relative;
        }
    }
}

if ($problems !== []) {
    fwrite(
        STDERR,
        "[FAIL] Artist-specific demo home-page copy remains in provisioning paths:\n"
    );

    foreach ($problems as $problem) {
        fwrite(STDERR, "[FAIL]  - {$problem}\n");
    }

    exit(1);
}

echo "[PASS] New tenant home-page defaults contain no artist-specific demo statement.\n";

// End of file.
