<?php

/**
 * Long-running wrapper for the background job run-once script.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$interval = max(1, (int) (getenv('ARTSFOLIO_BACKGROUND_WORKER_SLEEP_SECONDS') ?: 5));

while (true) {
    passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/scripts/workers/run_once.php'));
    sleep($interval);
}

// End of file.
