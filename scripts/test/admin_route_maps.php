<?php

declare(strict_types=1);

/**
 * Smoke test for admin route map controllers.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$files = [
    $root . '/app/Http/Controllers/Platform/Admin/RoutesController.php',
    $root . '/app/Http/Controllers/Tenant/Admin/RoutesController.php',
];

foreach ($files as $file) {
    $contents = file_get_contents($file);

    if ($contents === false || !str_contains($contents, 'AdminLayout') || !str_contains($contents, '/admin/routes')) {
        fwrite(STDERR, "Route map controller did not contain expected content: {$file}\n");
        exit(1);
    }
}

echo "Admin route maps smoke test passed.\n";

// End of file.
