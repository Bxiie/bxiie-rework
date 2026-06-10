<?php

declare(strict_types=1);

/**
 * Manual verification script for runtime environment safety checks.
 */

use App\Support\AppEnvironment;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$environment = AppEnvironment::fromEnv();

echo json_encode([
    'environment' => $environment->name(),
    'is_local' => $environment->isLocal(),
    'is_production' => $environment->isProduction(),
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
