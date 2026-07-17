<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$cssPath = $root . '/public/assets/tenant-admin.css';
$controllerPath = $root . '/app/Http/Controllers/Platform/HelpController.php';

foreach ([$cssPath, $controllerPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "[FAIL] Missing required file: {$path}\n");
        exit(1);
    }
}

$css = (string) file_get_contents($cssPath);
$controller = (string) file_get_contents($controllerPath);
$errors = [];

$requiredCss = [
    'body.platform-help-page .tenant-admin-sidebar',
    'background: #171717 !important;',
    'color: #ffffff !important;',
    'color: #d8d8d8 !important;',
    'background: #ffffff !important;',
    'outline: 3px solid #ffffff !important;',
    'Platform help sidebar high-contrast override.',
];

foreach ($requiredCss as $marker) {
    if (!str_contains($css, $marker)) {
        $errors[] = "Missing CSS marker: {$marker}";
    }
}

if (!str_contains(
    $controller,
    '/assets/tenant-admin.css?v=20260717-help-final-contrast'
)) {
    $errors[] = 'HelpController does not use the contrast cache-busting version.';
}

if ($errors !== []) {
    fwrite(STDERR, "[FAIL] Help sidebar contrast static check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "[FAIL]  - {$error}\n");
    }
    exit(1);
}

echo "[PASS] Help sidebar contrast static check passed.\n";

// End of file.
