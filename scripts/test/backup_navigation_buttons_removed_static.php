<?php

declare(strict_types=1);

/**
 * Prevents redundant cross-navigation buttons from returning.
 */

$root = dirname(__DIR__, 2);
$operations = (string) file_get_contents(
    $root . '/app/Http/Controllers/Platform/Admin/OperationsController.php'
);
$backups = (string) file_get_contents(
    $root . '/app/Http/Controllers/Platform/Admin/BackupsController.php'
);

$failures = [];

foreach (['View backups', 'View Backups'] as $marker) {
    if (str_contains($operations, $marker)) {
        $failures[] = "OperationsController still contains: {$marker}";
    }
}

if (str_contains($backups, 'Back to System Operations')) {
    $failures[] = 'BackupsController still contains Back to System Operations.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Backup navigation button removal check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Redundant backup navigation buttons are removed.\n";

// End of file.
