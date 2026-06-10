<?php

declare(strict_types=1);

/**
 * Smoke test for platform admin list screens using AdminLayout.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$files = [
    $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php',
    $root . '/app/Http/Controllers/Platform/Admin/EmailOutboxController.php',
    $root . '/app/Http/Controllers/Platform/Admin/AuditLogController.php',
];

foreach ($files as $file) {
    $contents = file_get_contents($file);

    if ($contents === false || !str_contains($contents, 'AdminLayout')) {
        fwrite(STDERR, "Expected AdminLayout reference in {$file}\n");
        exit(1);
    }

    if (!str_contains($contents, 'admin-table')) {
        fwrite(STDERR, "Expected admin-table class in {$file}\n");
        exit(1);
    }
}

echo "Platform admin layout smoke test passed.\n";

// End of file.
