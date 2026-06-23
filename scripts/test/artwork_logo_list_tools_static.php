<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Http/Controllers/Tenant/Admin/ArtworksController.php' => ['m.uuid AS primary_media_uuid', 'class="artwork-edit-preview"', "'&variant=large'"],
    'public/assets/auth.css' => ['width: min(390px, 88vw)'],
    'public/assets/platform.css' => ['.platform-header .compact-logo img', 'width: min(285px, 56vw)', '.artwork-edit-preview'],
    'app/Http/Controllers/Tenant/Admin/PortfolioSectionsController.php' => ['<table class="admin-table"'],
    'app/Http/Controllers/Tenant/Admin/ArtworkPlacementController.php' => ['class="admin-table sortable-artworks"'],
    'app/Http/Controllers/Platform/Admin/JobsController.php' => ['<table class="admin-table"'],
    'app/Http/Controllers/Platform/Admin/EmailOutboxController.php' => ['<table class="admin-table"'],
    'app/Http/Controllers/Platform/Admin/StatsController.php' => ['<table class="admin-table"'],
    'app/Http/Controllers/Platform/Admin/AuditLogController.php' => ['<table class="admin-table"'],
    'app/Http/Controllers/Platform/Admin/TenantsController.php' => ['<table class="admin-table"'],
    'app/Http/View/AdminLayout.php' => ['admin-table-tools.js?v=20260623-logo-list-tools'],
    'app/Http/View/TenantAdminLayout.php' => ['admin-table-tools.js?v=20260623-logo-list-tools'],
];
foreach ($checks as $relative => $needles) {
    $source = file_get_contents($root . '/' . $relative);
    if ($source === false) {
        fwrite(STDERR, "[FAIL] Could not read {$relative}.\n");
        exit(1);
    }
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            fwrite(STDERR, "[FAIL] Missing {$needle} in {$relative}.\n");
            exit(1);
        }
    }
}
echo "[PASS] Artwork preview, logo, and list-tool checks passed.\n";

// End of file.
