<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Platform/HelpController.php';

if (!is_file($controllerPath)) {
    fwrite(STDERR, "[FAIL] Missing HelpController.php.\\n");
    exit(1);
}

$source = (string) file_get_contents($controllerPath);
$errors = [];

$markers = [
    'HELP_SIDEBAR_INLINE_CONTRAST',
    '<style id="help-sidebar-inline-contrast">',
    '.tenant-admin-sidebar *',
    'color: #ffffff !important;',
    'opacity: 1 !important;',
    'background: #ffffff !important;',
    'outline: 3px solid #ffffff !important;',
];

foreach ($markers as $marker) {
    if (!str_contains($source, $marker)) {
        $errors[] = "Missing marker: {$marker}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "[FAIL] Help sidebar inline contrast check failed:\\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "[FAIL]  - {$error}\\n");
    }
    exit(1);
}

echo "[PASS] Help sidebar inline contrast check passed.\\n";

// End of file.
