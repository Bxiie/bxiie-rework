<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$css = (string) file_get_contents($root . '/public/assets/tenant-admin.css');
$upload = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/Admin/ArtworkUploadController.php'
);

$failures = [];

foreach ([
    '.admin-checkbox-grid',
    '.admin-checkbox-row',
    'align-items: flex-start',
    'gap: .7rem',
    'label:has(> input[type="checkbox"])',
    'grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr))',
] as $marker) {
    if (!str_contains($css, $marker)) {
        $failures[] = "tenant-admin.css missing marker: {$marker}";
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
