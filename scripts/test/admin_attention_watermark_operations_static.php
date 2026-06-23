<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Http/View/TenantAdminNav.php' => ['tenant-nav-badge', 'email_signups_last_viewed_at', "status = 'new'"],
    'app/Tenant/Media/WatermarkService.php' => ['watermark_enabled', 'watermark_position', 'watermark_opacity'],
    'app/Http/Controllers/Tenant/MediaController.php' => ['WatermarkService', "variantKey !== 'thumb'"],
    'app/Http/Controllers/Tenant/Admin/SettingsController.php' => ["'watermark'", 'watermark_format', 'watermark_color'],
    'app/Http/View/PlatformChrome.php' => ['public static function topNavigation', "'developers'", "'contact'"],
    'public/assets/admin-table-tools.js' => ['admin-table-tools', 'aria-sort'],
    'app/Http/Controllers/Platform/Admin/OperationsController.php' => ['Trend/check start', 'Apply range', 'Page '],
];
foreach ($checks as $file => $needles) {
    $contents = file_get_contents($root . '/' . $file);
    foreach ($needles as $needle) {
        if (!is_string($contents) || !str_contains($contents, $needle)) {
            fwrite(STDERR, "Missing {$needle} in {$file}.\n"); exit(1);
        }
    }
}
echo "Admin attention, watermark, navigation, table tools, and operations static checks passed.\n";

// End of file.
