<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [
    'app/Http/Controllers/Platform/HelpController.php' => ["'new-admin-tour'", "'tenant-admin-functions'", "'training-videos'", 'Dashboard</strong>:', 'Sales Analytics</strong>:', 'Routes</strong>:'],
    'app/Platform/Signup/TenantSignupService.php' => ['$adminUrl = $tenantBaseUrl . \'/admin\';', '$tourUrl = $tenantBaseUrl . \'/admin/getting-started\';', '$functionsUrl = $platformUrl . \'/help/tenant-admin-functions\';', '$videosUrl = $platformUrl . \'/help/training-videos\';', 'Open your admin'],
    'app/Http/Controllers/Tenant/Admin/GettingStartedController.php' => ['href="/admin/artwork/upload"', 'href="/help/tenant-admin-functions"', 'href="/help/training-videos"'],
    'docs/user/tenant-admin-help.md' => ['/help/tenant-admin-functions', '/help/sales', '/help/audit', '/help/stats'],
    'docs/user/training-videos/index.md' => ['Video links will be added later'],
];

$failures = [];
foreach ($checks as $relativePath => $markers) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path)) { $failures[] = "Missing file: {$relativePath}"; continue; }
    $contents = (string) file_get_contents($path);
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) { $failures[] = "Missing marker in {$relativePath}: {$marker}"; }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Tenant help onboarding static check failed:\n");
    foreach ($failures as $failure) { fwrite(STDERR, "[FAIL]  - {$failure}\n"); }
    exit(1);
}

fwrite(STDOUT, "[PASS] Tenant help onboarding static check passed.\n");

// End of file.
