<?php

declare(strict_types=1);

/**
 * Regression test for admin dashboard database-root resolution.
 *
 * The dashboard controllers live under app/Http/Controllers/{Platform,Tenant}/Admin.
 * From there, dirname(__DIR__, 5) is the project root. dirname(__DIR__, 6) points
 * outside the application and causes Database::connect() to look for a missing
 * config/database.php, which makes dashboard counts quietly fall to zero.
 */

$root = dirname(__DIR__, 2);
$targets = [
    'app/Http/Controllers/Platform/Admin/DashboardController.php',
    'app/Http/Controllers/Tenant/Admin/DashboardController.php',
];

foreach ($targets as $relativePath) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing dashboard controller: {$relativePath}\n");
        exit(1);
    }

    $source = file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "Unable to read dashboard controller: {$relativePath}\n");
        exit(1);
    }

    if (str_contains($source, 'Database::connect(dirname(__DIR__, 6))')) {
        fwrite(STDERR, "Dashboard controller still points Database::connect() outside the project root: {$relativePath}\n");
        exit(1);
    }

    if (!str_contains($source, 'Database::connect(dirname(__DIR__, 5))')) {
        fwrite(STDERR, "Dashboard controller does not contain the expected project-root database path: {$relativePath}\n");
        exit(1);
    }
}

echo "Dashboard controller database root regression passed.\n";

// End of file.
