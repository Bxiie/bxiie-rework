<?php

declare(strict_types=1);

/**
 * Regression checks for navigation background watermark exclusion.
 */

$root = dirname(__DIR__, 2);
$home = file_get_contents(
    $root . '/app/Http/Controllers/Tenant/HomeController.php'
);
$admin = file_get_contents(
    $root . '/app/Http/View/TenantAdminLayout.php'
);
$media = file_get_contents(
    $root . '/app/Http/Controllers/Tenant/MediaController.php'
);

if ($home === false || $admin === false || $media === false) {
    fwrite(
        STDERR,
        "[FAIL] Could not read navigation background source files.
"
    );
    exit(1);
}

$homeNeedles = [
    "'menu_media_uuid', '--menu-bg-image', true",
    'bool $backgroundUsage = false',
    "\$backgroundUsage ? '&usage=background' : ''",
];

$adminNeedles = [
    "'menu_media_uuid', ''), '--menu-bg-image', true",
    'bool $backgroundUsage = false',
    "\$backgroundUsage ? '&usage=background' : ''",
];

$mediaNeedles = [
    "\$_GET['usage']",
    "=== 'background'",
    '&& !$isBackgroundRequest',
];

foreach ($homeNeedles as $needle) {
    if (!str_contains($home, $needle)) {
        fwrite(
            STDERR,
            "[FAIL] HomeController.php missing: {$needle}
"
        );
        exit(1);
    }
}

foreach ($adminNeedles as $needle) {
    if (!str_contains($admin, $needle)) {
        fwrite(
            STDERR,
            "[FAIL] TenantAdminLayout.php missing: {$needle}
"
        );
        exit(1);
    }
}

foreach ($mediaNeedles as $needle) {
    if (!str_contains($media, $needle)) {
        fwrite(
            STDERR,
            "[FAIL] MediaController.php missing: {$needle}
"
        );
        exit(1);
    }
}

echo "[PASS] Navigation background watermark exclusion checks passed.
";

// End of file.
