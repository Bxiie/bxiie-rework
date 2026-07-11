#!/usr/bin/php
<?php

/**
 * Prevent source-file footer markers from leaking into outgoing email bodies.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$templateRoot = $root . '/template/email';

if (!is_dir($templateRoot)) {
    fwrite(STDERR, "[FAIL] Missing email-template directory: {$templateRoot}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS)
);

$problems = [];
$checked = 0;

foreach ($iterator as $file) {
    if (!$file->isFile() || str_starts_with($file->getFilename(), '._')) {
        continue;
    }

    $path = $file->getPathname();
    $body = file_get_contents($path);
    if ($body === false) {
        $problems[] = "Could not read {$path}";
        continue;
    }

    $checked++;

    if (preg_match('/^[ \t]*# End of file\.[ \t]*$/m', $body) === 1) {
        $problems[] = str_replace($root . '/', '', $path);
    }
}

if ($problems !== []) {
    fwrite(STDERR, "[FAIL] Email templates contain '# End of file.' markers:\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, "[FAIL]  - {$problem}\n");
    }
    exit(1);
}

echo "[PASS] {$checked} email templates contain no source-file footer markers.\n";

// End of file.
