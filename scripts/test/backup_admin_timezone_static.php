<?php

declare(strict_types=1);

/**
 * Regression checks for localized backup timestamps.
 */

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents(
    $root . '/app/Http/Controllers/Platform/Admin/BackupsController.php'
);

$required = [
    '$this->statusTime($status)',
    "str_ends_with((string) \$key, '_at')",
    '$this->displaySystemTime((string) $values[1])',
    '$this->displaySystemTime((string) $values[2])',
    "new \\DateTimeZone('UTC')",
    "\$GLOBALS['artsfolio_user_timezone']",
    "format('M j, Y g:i:s A T')",
];

$failures = [];

foreach ($required as $marker) {
    if (!str_contains($controller, $marker)) {
        $failures[] = "BackupsController missing marker: {$marker}";
    }
}

$legacy = [
    "AdminLayout::escape((string) (\$status['checked_at'] ?? 'Never'))",
    "AdminLayout::escape(trim((string) \$values[1]))",
    "AdminLayout::escape(trim((string) \$values[2]))",
];

foreach ($legacy as $marker) {
    if (str_contains($controller, $marker)) {
        $failures[] = "BackupsController still renders an unlocalized timestamp: {$marker}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Backup administrator time-zone check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Backup timestamps use the administrator time zone.\n";

// End of file.
