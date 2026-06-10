<?php

declare(strict_types=1);

/**
 * Syntax/structure smoke test for admin dashboard layout dependencies.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$files = [
    $root . '/app/Http/Controllers/Platform/Admin/DashboardController.php',
    $root . '/app/Http/Controllers/Tenant/Admin/DashboardController.php',
    $root . '/app/Http/View/AdminLayout.php',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing file: {$file}\n");
        exit(1);
    }

    $contents = file_get_contents($file);

    if ($contents === false || !str_contains($contents, 'AdminLayout')) {
        fwrite(STDERR, "Expected AdminLayout reference in {$file}\n");
        exit(1);
    }
}

echo "Admin dashboard layout smoke test passed.\n";

// End of file.
