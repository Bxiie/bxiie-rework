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
        'content-colors-bg-controls-layout',
        'content-colors-bg-image-picker-unavailable-v19',
        'input[type="color"]',
        '::-webkit-color-swatch',
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

    $smallSwatchHideMarkers = [
        'input[type="color"] + .tenant-color-swatch',
        '.tenant-color-swatch[aria-hidden="true"]',
        '.color-preview-swatch',
    ];

    $hasSmallSwatchHide = false;
    foreach ($smallSwatchHideMarkers as $marker) {
        if (strpos($css, $marker) !== false) {
            $hasSmallSwatchHide = true;
            break;
        }
    }

    if (!$hasSmallSwatchHide) {
        $errors[] = 'tenant-admin.css missing a rule to hide redundant small color swatches.';
    }

    $pickerMarkers = [
        'selected-image',
        'image-picker',
        'selected-media',
        'background-image',
        'Change image',
        'Image unavailable',
    ];

    $hasPickerRepairMarker = false;
    foreach ($pickerMarkers as $marker) {
        if (strpos($css, $marker) !== false) {
            $hasPickerRepairMarker = true;
            break;
        }
    }

    if (!$hasPickerRepairMarker) {
        $errors[] = 'tenant-admin.css missing selected image picker layout repair markers.';
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
