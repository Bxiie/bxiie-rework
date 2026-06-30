<?php

/**
 * Static regression checks for tenant visual settings and session-cookie aliases.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Http/Support/SessionCookie.php' => ['function issueHeaders', 'function expireHeaders'],
    'app/Http/Controllers/Tenant/HomeController.php' => ['menu_background_enabled', 'v=20260602a', 'data-af-form-purpose="contact"'],
    'app/Http/View/TenantAdminLayout.php' => ['backgroundCssVariables', 'menu_background_enabled', '--site-bg-image'],
    'app/Http/Controllers/Tenant/Admin/SettingsController.php' => ['menu_background_enabled', 'Suppress panel'],
    'public/assets/site.css' => ['--menu-panel-padding', 'Tenant visual authority repair'],
    'public/assets/tenant-admin.css' => ['Tenant admin visual authority repair', '--menu-panel-padding'],
];

foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing file: {$relative}\n");
        exit(1);
    }

    $content = file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains((string) $content, $needle)) {
            fwrite(STDERR, "Missing marker in {$relative}: {$needle}\n");
            exit(1);
        }
    }
}

fwrite(STDOUT, "Tenant visual settings static checks passed.\n");

// End of file.
