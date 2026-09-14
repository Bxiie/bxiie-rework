<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$css = (string) file_get_contents($root . '/public/assets/tenant-admin.css');
$upload = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/Admin/ArtworkUploadController.php'
);
$edit = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php'
);
$layout = (string) file_get_contents($root . '/app/Http/View/TenantAdminLayout.php');

$failures = [];

foreach ([
    '.admin-checkbox-grid',
    '.admin-checkbox-row',
    'align-items: flex-start',
    'gap: .7rem',
    'label:has(> input[type="checkbox"])',
    'grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr))',
    '.artwork-portfolio-section-options',
    '.tenant-admin-panel fieldset .artwork-portfolio-section-option',
    '.tenant-admin-panel fieldset .artwork-portfolio-section-option:focus-within',
    '.tenant-admin-panel fieldset .artwork-portfolio-section-option:has(input:checked)',
    'border: 2px solid #6f675c !important',
    'display: flex !important',
    'width: 1.2rem !important',
] as $marker) {
    if (!str_contains($css, $marker)) {
        $failures[] = "tenant-admin.css missing marker: {$marker}";
    }
}

if (!str_contains($layout, 'tenant-admin.css?v=20260914-artwork-section-lines')) {
    $failures[] = 'TenantAdminLayout must refresh the tenant-admin stylesheet cache key.';
}

foreach ([
    'class="artwork-portfolio-section-options"',
    'class=\"artwork-portfolio-section-option\"',
    'class="artwork-portfolio-section-option homepage-special-section-option"',
    '<span>{$sectionName}</span>',
    '<span>Home Page</span>',
] as $marker) {
    if (!str_contains($edit, $marker)) {
        $failures[] = "ArtworksController missing marker: {$marker}";
    }
}

foreach ([
    'class="admin-checkbox-row"',
    '<span>Portfolio Images</span>',
    '<span>Site Images</span>',
] as $marker) {
    if (!str_contains($upload, $marker)) {
        $failures[] = "ArtworkUploadController missing marker: {$marker}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Admin checkbox clarity static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Admin checkbox labels remain visually attached to their controls.\n";

// End of file.
