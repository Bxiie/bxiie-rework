<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

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
    $required = [
        'content-about-contact-wide-v24',
        'textarea[name*="about" i][name*="html" i]',
        'textarea[name*="contact" i][name*="html" i]',
        'grid-template-columns: minmax(0, 1fr)',
        '.site-image-picker-summary',
    ];

    foreach ($required as $marker) {
        if (strpos($css, $marker) === false) {
            $errors[] = 'tenant-admin.css missing content about/contact full-width marker: ' . $marker;
        }
    }
}

$contentController = $root . '/app/Http/Controllers/Tenant/Admin/ContentController.php';
if (!is_file($contentController)) {
    $errors[] = 'ContentController.php not found.';
} else {
    $content = file_get_contents($contentController);
    foreach (['About page', 'Contact page', 'About content HTML', 'Contact details HTML'] as $marker) {
        if (strpos($content, $marker) === false) {
            $errors[] = 'ContentController.php missing expected content page marker: ' . $marker;
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
