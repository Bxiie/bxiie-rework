<?php

declare(strict_types=1);

$root = $argv[1] ?? dirname(__DIR__, 2);
$layoutPath = $root . '/app/Http/View/AdminLayout.php';
$cssPath = $root . '/public/assets/admin-shell-refactor.css';
$assetPath = $root . '/public/assets/artsfol-wordmark.png';

$failures = [];
foreach ([$layoutPath, $cssPath, $assetPath] as $path) {
    if (!is_file($path)) {
        $failures[] = 'Missing required file: ' . $path;
    }
}

if ($failures === []) {
    $layout = (string) file_get_contents($layoutPath);
    $css = (string) file_get_contents($cssPath);

    foreach ([
        'platform-admin-logo platform-admin-wordmark-card',
        '/assets/artsfol-wordmark.png?v=20260721-admin-visible-v2',
        'aria-label="ArtsFol.io platform admin"',
        'alt="ArtsFol.io"',
    ] as $marker) {
        if (!str_contains($layout, $marker)) {
            $failures[] = 'AdminLayout missing marker: ' . $marker;
        }
    }

    if (preg_match('/platform-admin-logo[^>]*>\s*<img[^>]+logo_2\.png/i', $layout)) {
        $failures[] = 'Platform admin header still references logo_2.png.';
    }

    foreach ([
        'ArtsFol.io platform-admin visible wordmark card v2.',
        '.platform-admin-topbar .platform-admin-wordmark-card',
        'background: #fff !important;',
        'filter: none !important;',
        'visibility: visible !important;',
    ] as $marker) {
        if (!str_contains($css, $marker)) {
            $failures[] = 'Admin shell CSS missing marker: ' . $marker;
        }
    }

    $image = @getimagesize($assetPath);
    if ($image === false || ($image[2] ?? null) !== IMAGETYPE_PNG) {
        $failures[] = 'Wordmark asset is not a readable PNG.';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Platform admin wordmark visibility check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "[PASS] Platform admin wordmark visibility check passed.\n");
