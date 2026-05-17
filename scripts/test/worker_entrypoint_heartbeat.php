<?php

declare(strict_types=1);

/**
 * Verifies that the shared worker heartbeat helper can be loaded and writes a pulse.
 */

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';
require_once $root . '/scripts/workers/heartbeat.php';

use App\Platform\Workers\WorkerHeartbeatRepository;
use App\Support\Database;

artsfolio_worker_heartbeat(
    workerName: 'manual-entrypoint-heartbeat-test',
    status: 'alive',
    details: ['source' => 'scripts/test/worker_entrypoint_heartbeat.php'],
);

$repo = new WorkerHeartbeatRepository(Database::connect($root));
$workers = $repo->latest(20);

$found = false;
foreach ($workers as $worker) {
    if ((string) $worker['worker_name'] === 'manual-entrypoint-heartbeat-test') {
        $found = true;
        break;
    }
}

if (!$found) {
    fwrite(STDERR, "Worker entrypoint heartbeat was not recorded.\n");
    exit(1);
}

echo "Worker entrypoint heartbeat recorded.\n";

// End of file.
