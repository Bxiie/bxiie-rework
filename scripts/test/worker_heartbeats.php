<?php

declare(strict_types=1);

/**
 * Manual verification script for worker heartbeat repository.
 */

use App\Platform\Workers\WorkerHeartbeatRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$repo = new WorkerHeartbeatRepository(Database::connect($root));

$repo->beat(
    workerName: 'manual-test-worker',
    hostName: gethostname() ?: 'unknown',
    processId: getmypid(),
    status: 'alive',
    details: ['source' => 'scripts/test/worker_heartbeats.php'],
);

$workers = $repo->latest(10);

$found = false;
foreach ($workers as $worker) {
    if ((string) $worker['worker_name'] === 'manual-test-worker') {
        $found = true;
    }
}

if (!$found) {
    fwrite(STDERR, "Worker heartbeat not found.\n");
    exit(1);
}

echo json_encode([
    'found' => $found,
    'workers' => $workers,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
