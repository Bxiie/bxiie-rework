<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$files = [
    'routes' => $root . '/app/Http/Routes/tenant.php',
    'upload' => $root . '/app/Http/Controllers/Tenant/Admin/ArtworkUploadController.php',
    'sections' => $root . '/app/Http/Controllers/Tenant/Admin/PortfolioSectionsController.php',
    'dashboard' => $root . '/app/Http/Controllers/Tenant/Admin/ContributorDashboardController.php',
];

$contents = [];
$failures = [];

foreach ($files as $key => $path) {
    if (!is_file($path)) {
        $failures[] = "Missing required file: {$path}";
        continue;
    }

    $contents[$key] = (string) file_get_contents($path);
}

$required = [
    'routes' => [
        '/admin/contributor',
        'Tenant contributor access required.',
        "'editor', 'user'",
        "Location' => '/admin/contributor'",
    ],
    'upload' => [
        "'status' => \$this->isTenantAdmin(\$currentUser, \$tenant) ? \$this->newArtworkDefaultStatus(\$tenant) : 'draft'",
        'Contributor uploads are saved as drafts',
        'private function isTenantAdmin',
    ],
    'sections' => [
        "['tenant_owner', 'tenant_admin', 'owner', 'admin', 'editor', 'user']",
        'Contributors cannot alter an active portfolio section.',
        ": 'hidden';",
        'This section will be saved as a draft.',
    ],
    'dashboard' => [
        'Upload draft artwork',
        'Create draft section',
        'Suggest via curation',
        'Your work stays in draft.',
    ],
];

foreach ($required as $key => $markers) {
    if (!isset($contents[$key])) {
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents[$key], $marker)) {
            $failures[] = "{$files[$key]} missing marker: {$marker}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Tenant contributor access static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Tenant contributors receive draft-only workflow access.\n";

// End of file.
