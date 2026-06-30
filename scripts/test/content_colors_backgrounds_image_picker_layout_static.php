<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$cssCandidates = [
    $root . '/public/assets/tenant-admin.css',
    $root . '/public/assets/css/tenant-admin.css',
];

$cssPath = null;
foreach ($cssCandidates as $candidate) {
    if (is_file($candidate)) {
        $cssPath = $candidate;
        break;
    }
}

$errors = [];

if ($cssPath === null) {
    $errors[] = 'tenant-admin.css not found in expected public locations.';
} else {
    $css = file_get_contents($cssPath);

    $requiredCssMarkers = [
        'site-image-picker-render-v20',
        'site-image-picker-summary-grid-v22',
        'site-image-picker-compact-summary-grid-v23',
        'grid-template-areas',
        '.site-image-picker-summary',
        '.site-image-picker-current',
        '.site-image-picker-image-wrap',
        '.site-image-picker-image-fallback',
        '.site-image-picker-change-button',
        'input[type="color"]',
        'input[type="color"] + .tenant-color-swatch',
        '.tenant-admin-panel .tenant-palette-button',
        '.tenant-palette-preview',
        '.tenant-palette-toolbar',
        '.tenant-palette-swatch',
    ];

    foreach ($requiredCssMarkers as $marker) {
        if (strpos($css, $marker) === false) {
            $errors[] = 'tenant-admin.css missing marker: ' . $marker;
        }
    }
}

$rendererFiles = [
    'app/Http/Controllers/Tenant/Admin/SettingsController.php',
    'app/Http/Controllers/Tenant/Admin/ContentController.php',
];

foreach ($rendererFiles as $rendererFile) {
    $path = $root . '/' . $rendererFile;
    if (!is_file($path)) {
        $errors[] = 'Renderer file missing: ' . $rendererFile;
        continue;
    }

    $contents = file_get_contents($path);

    if (strpos($contents, 'site-image-picker-image-wrap') === false) {
        $errors[] = $rendererFile . ' missing site image picker thumbnail wrapper.';
    }

    if (strpos($contents, 'site-image-picker-change-button') === false) {
        $errors[] = $rendererFile . ' missing site image picker change button.';
    }

    if (strpos($contents, 'this.nextElementSibling.hidden=false') !== false) {
        $errors[] = $rendererFile . ' still unhides the Image unavailable fallback.';
    }

    if (strpos($contents, '>Image unavailable<') !== false) {
        $errors[] = $rendererFile . ' still renders visible Image unavailable fallback text.';
    }

    if (strpos($contents, 'site-image-picker-image-fallback') !== false) {
        $errors[] = $rendererFile . ' still renders the old image fallback span.';
    }
}

$testFiles = glob($root . '/scripts/test/*.php') ?: [];
foreach ($testFiles as $testFile) {
    $contents = file_get_contents($testFile);
    if (preg_match('/file_get_contents\([^)]*tenant-admin\.css\?v=/', $contents) === 1) {
        $errors[] = 'Static test uses cache-busted tenant-admin.css as a filesystem path: ' . str_replace($root . '/', '', $testFile);
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Content/colors background controls layout static check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

echo "Content/colors background controls layout static checks passed.\n";
