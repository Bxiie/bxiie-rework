<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$contentPath = $root . '/app/Http/Controllers/Tenant/Admin/ContentController.php';
if (!is_file($contentPath)) {
    $errors[] = 'ContentController.php not found.';
} else {
    $content = file_get_contents($contentPath);
    $requiredContentMarkers = [
        'admin-form-grid content-page-grid',
        'admin-panel admin-panel-wide content-page-section content-page-about-section',
        'admin-panel admin-panel-wide content-page-section content-page-contact-section',
        'About page',
        'Contact page',
        'About content HTML',
        'Contact details HTML',
    ];

    foreach ($requiredContentMarkers as $marker) {
        if (strpos($content, $marker) === false) {
            $errors[] = 'ContentController.php missing content full-width marker: ' . $marker;
        }
    }
}

$cssCandidates = [
    $root . '/public/assets/tenant-admin.css',
    $root . '/public/assets/css/tenant-admin.css',
];

$css = null;
foreach ($cssCandidates as $candidate) {
    if (is_file($candidate)) {
        $css = file_get_contents($candidate);
        break;
    }
}

if ($css === null) {
    $errors[] = 'tenant-admin.css not found in expected public locations.';
} else {
    $requiredCssMarkers = [
        'content-page-grid-v25',
        '.content-page-grid',
        'grid-template-columns: minmax(0, 1fr)',
        'content-page-about-section',
        'content-page-contact-section',
        '.content-page-grid .site-image-picker-summary',
    ];

    foreach ($requiredCssMarkers as $marker) {
        if (strpos($css, $marker) === false) {
            $errors[] = 'tenant-admin.css missing content full-width marker: ' . $marker;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Content about/contact full-width static check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

echo "Content about/contact full-width static checks passed.\n";
