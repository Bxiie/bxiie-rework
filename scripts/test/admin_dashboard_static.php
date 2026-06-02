<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$platform = file_get_contents($root . '/app/Http/Controllers/Platform/Admin/DashboardController.php') ?: '';
$tenant = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/DashboardController.php') ?: '';
$css = file_get_contents($root . '/public/assets/tenant-admin.css') ?: '';

$requiredPlatform = [
    '30-day GMV',
    '30-day commission',
    'Recent sales',
    'Plans and selling controls',
    'Queued / failed jobs',
];
$requiredTenant = [
    'Published artworks',
    'For sale',
    '30-day views',
    'Open orders',
    'Needs attention',
    'Recent contact messages',
];
$requiredCss = [
    '.dashboard-metric-grid',
    '.dashboard-metric-card',
    '.dashboard-split-grid',
    '.dashboard-plan-panel',
];

foreach ($requiredPlatform as $needle) {
    if (!str_contains($platform, $needle)) {
        fwrite(STDERR, "Missing platform dashboard marker: {$needle}\n");
        exit(1);
    }
}

foreach ($requiredTenant as $needle) {
    if (!str_contains($tenant, $needle)) {
        fwrite(STDERR, "Missing tenant dashboard marker: {$needle}\n");
        exit(1);
    }
}

foreach ($requiredCss as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, "Missing dashboard CSS marker: {$needle}\n");
        exit(1);
    }
}

print "Admin dashboard static checks passed.\n";

// End of file.
