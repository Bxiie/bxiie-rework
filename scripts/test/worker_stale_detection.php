<?php

declare(strict_types=1);

/**
 * Manual verification script for stale worker heartbeat detection data.
 */

use App\Platform\Workers\WorkerHeartbeatRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$repo = new WorkerHeartbeatRepository($pdo);

$repo->beat(
    workerName: 'manual-stale-worker-test',
    hostName: gethostname() ?: 'unknown',
    processId: getmypid(),
    status: 'alive',
    details: ['source' => 'scripts/test/worker_stale_detection.php'],
);

$pdo->prepare(
    "UPDATE worker_heartbeats
     SET last_seen_at = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE)
     WHERE worker_name = :worker_name"
)->execute(['worker_name' => 'manual-stale-worker-test']);

$workers = $repo->latest(100);
$found = false;

foreach ($workers as $worker) {
    if ((string) $worker['worker_name'] === 'manual-stale-worker-test') {
        $found = true;
        break;
    }
}

if (!$found) {
    fwrite(STDERR, "Manual stale worker row not found.\n");
    exit(1);
}

echo json_encode([
    'worker_name' => 'manual-stale-worker-test',
    'expected_effective_status' => 'stale',
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
