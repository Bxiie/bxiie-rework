<?php

declare(strict_types=1);

/**
 * Manual verification script for fixed-window rate limiting.
 */

use App\Platform\Security\RateLimiter;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$limiter = new RateLimiter(Database::connect($root));
$key = 'manual-test:' . bin2hex(random_bytes(4));
$windowSeconds = 60;

$result = [
    'first_allowed' => $limiter->allow($key, 2, $windowSeconds),
    'second_allowed' => $limiter->allow($key, 2, $windowSeconds),
    'third_allowed' => $limiter->allow($key, 2, $windowSeconds),
    'attempts' => $limiter->attempts($key, $windowSeconds),
];

if ($result['first_allowed'] !== true || $result['second_allowed'] !== true || $result['third_allowed'] !== false) {
    fwrite(STDERR, "Unexpected rate limiter behavior.\n");
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
