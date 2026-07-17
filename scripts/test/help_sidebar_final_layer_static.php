<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$css = (string) file_get_contents($root . '/public/assets/tenant-admin.css');
$controller = (string) file_get_contents($root . '/app/Http/Controllers/Platform/HelpController.php');
$errors = [];
foreach ([
    'PLATFORM_HELP_FINAL_SIDEBAR_CONTRAST',
    'body.tenant-admin-page:not(.platform-help-page) .tenant-admin-sidebar',
    'body.tenant-admin-page.platform-help-page .tenant-admin-sidebar *',
    'color: #ffffff !important;',
    'opacity: 1 !important;',
] as $marker) {
    if (!str_contains($css, $marker)) {
        $errors[] = "Missing CSS marker: {$marker}";
    }
}
if (!str_contains($controller, '/assets/tenant-admin.css?v=20260717-help-final-contrast')) {
    $errors[] = 'HelpController stylesheet version was not updated.';
}
if ($errors !== []) {
    fwrite(STDERR, "[FAIL] Help final sidebar contrast check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "[FAIL]  - {$error}\n");
    }
    exit(1);
}
echo "[PASS] Help final sidebar contrast check passed.\n";
// End of file.
