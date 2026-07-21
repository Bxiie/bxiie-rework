<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$marketing = file_get_contents($root . '/app/Http/Controllers/Platform/MarketingController.php');
$admin = file_get_contents($root . '/app/Http/View/AdminLayout.php');
$css = file_get_contents($root . '/public/assets/tenant-admin.css');
$failures = [];
foreach ([
    'home wordmark image' => [$marketing, 'class="platform-brand logo-brand"'],
    'home wordmark asset' => [$marketing, '/assets/artsfol-wordmark.png'],
    'admin wordmark asset' => [$admin, '/assets/artsfol-wordmark.png'],
    'admin white backplate marker' => [$css, 'ARTSFOLIO_PLATFORM_ADMIN_WORDMARK_WHITE_BACKPLATE'],
    'admin white background' => [$css, 'background: #fff;'],
] as $label => [$haystack, $needle]) {
    if (!str_contains((string) $haystack, $needle)) { $failures[] = $label; }
}
if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Platform wordmark placement check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "[PASS] Platform home/admin wordmark placement check passed.\n");
