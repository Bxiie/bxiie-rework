<?php

declare(strict_types=1);

/**
 * Smoke test for platform and tenant settings screens using AdminLayout.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$files = [
    $root . '/app/Http/Controllers/Platform/Admin/SettingsController.php',
    $root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php',
];

foreach ($files as $file) {
    $contents = file_get_contents($file);

    if ($contents === false || !str_contains($contents, 'AdminLayout')) {
        fwrite(STDERR, "Expected AdminLayout reference in {$file}\n");
        exit(1);
    }

    if (!str_contains($contents, 'admin-form')) {
        fwrite(STDERR, "Expected admin-form class in {$file}\n");
        exit(1);
    }
}

echo "Admin settings layout smoke test passed.\n";

// End of file.
