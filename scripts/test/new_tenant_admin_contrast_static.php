<?php

declare(strict_types=1);

/**
 * Regression checks for readable Tenant Admin default colors.
 */

$root = dirname(__DIR__, 2);
$css = (string) file_get_contents($root . '/public/assets/tenant-admin.css');
$layout = (string) file_get_contents($root . '/app/Http/View/TenantAdminLayout.php');

$failures = [];

$required = [
    'Tenant admin final sidebar contrast layer.',
    'body.tenant-admin-page .tenant-admin-sidebar nav a',
    'var(--menu-text-color, var(--text-color, #1f1a14))',
    '.tenant-admin-sidebar-title span',
    '.tenant-admin-sidebar-upload:hover',
    'text-shadow: none !important;',
];

foreach ($required as $marker) {
    if (!str_contains($css, $marker)) {
        $failures[] = "tenant-admin.css missing marker: {$marker}";
    }
}

if (!str_contains(
    $layout,
    'tenant-admin.css?v=20260712-sidebar-contrast-v2'
)) {
    $failures[] = 'Tenant Admin stylesheet cache version was not updated.';
}

$finalMarker = strrpos($css, 'Tenant admin final sidebar contrast layer.');
$forcedWhite = strrpos(
    $css,
    ".tenant-admin-sidebar,\n.tenant-admin-sidebar a,\n.tenant-admin-brand {\n  color: #fff !important;"
);

if ($finalMarker === false) {
    $failures[] = 'Final contrast layer is missing.';
} elseif ($forcedWhite !== false && $finalMarker < $forcedWhite) {
    $failures[] = 'A forced-white rule appears after the final contrast layer.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] New tenant admin contrast check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] New tenant admin sidebar colors remain readable.\n";

// End of file.
