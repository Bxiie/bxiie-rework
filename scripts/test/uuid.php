<?php

declare(strict_types=1);

/**
 * Manual verification script for project UUID generation.
 */

use App\Support\Uuid;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$uuid = Uuid::v4();

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
    fwrite(STDERR, "Invalid UUID generated: {$uuid}\n");
    exit(1);
}

echo "Generated valid UUID: {$uuid}\n";

// End of file.
