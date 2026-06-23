<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Http/View/TenantAdminNav.php' => ['tenant-nav-badge', "status = 'new'", 'email_signups_last_viewed_at'],
    'app/Http/View/AdminLayout.php' => ["'contacts' => ['/platform/admin/contacts', 'Contacts']", "'email_signups' => ['/platform/admin/email-signups', 'Email Signups']", 'platformAttentionCounts'],
    'app/Http/Controllers/Platform/Admin/EmailSignupsController.php' => ['email_signups_last_viewed_at', "active: 'email_signups'"],
    'app/Tenant/Signup/EmailSignupRepository.php' => ['markAdminViewed', 'email_signups_last_viewed_at'],
    'app/Tenant/Media/WatermarkService.php' => ['watermark_enabled', 'watermark_format', 'watermark_position', 'watermark_opacity'],
    'app/Http/Controllers/Tenant/MediaController.php' => ['WatermarkService', "variantKey !== 'thumb'"],
    'app/Http/View/PlatformChrome.php' => ["'home' => ['/', 'Home']", "'artists' => ['/directory', 'Artists']", "'developers' => ['/developer', 'Developers']", "'contact' => ['/contact', 'Contact']"],
];
foreach ($checks as $file => $needles) {
    $contents = file_get_contents($root . '/' . $file);
    foreach ($needles as $needle) {
        if (!is_string($contents) || !str_contains($contents, $needle)) {
            fwrite(STDERR, "Missing {$needle} in {$file}.\n");
            exit(1);
        }
    }
}
echo "Admin notification, watermark, and canonical navigation checks passed.\n";

// End of file.
