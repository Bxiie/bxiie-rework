<?php

declare(strict_types=1);

/**
 * Regression coverage for the tenant-admin sidebar Upload Artwork action.
 */

$root = dirname(__DIR__, 2);
$layoutPath = $root . '/app/Http/View/TenantAdminLayout.php';
$cssPath = $root . '/public/assets/tenant-admin.css';

$layout = file_get_contents($layoutPath);
$css = file_get_contents($cssPath);

if ($layout === false || $css === false) {
    fwrite(STDERR, "Unable to read tenant-admin layout assets.\n");
    exit(1);
}

$button = '<a class="tenant-admin-sidebar-upload" href="/admin/artwork/upload">';
$title = '<div class="tenant-admin-sidebar-title">';
$buttonPosition = strpos($layout, $button);
$titlePosition = strpos($layout, $title);

if ($buttonPosition === false) {
    fwrite(STDERR, "Tenant-admin sidebar Upload Artwork button is missing.\n");
    exit(1);
}

if ($titlePosition === false || $buttonPosition > $titlePosition) {
    fwrite(STDERR, "Upload Artwork button must appear before the tenant name and signed-in details.\n");
    exit(1);
}

foreach ([
    '.tenant-admin-sidebar-upload',
    'background: var(--accent, #c9a85f)',
    'border-radius: 999px',
] as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, "Missing sidebar Upload Artwork styling: {$needle}\n");
        exit(1);
    }
}

if (!str_contains($layout, 'tenant-admin.css?v=20260623-sidebar-upload')) {
    fwrite(STDERR, "Tenant-admin stylesheet cache key was not updated.\n");
    exit(1);
}

echo "Tenant-admin sidebar Upload Artwork static checks passed.\n";

// End of file.
