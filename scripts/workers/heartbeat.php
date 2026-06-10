<?php

declare(strict_types=1);

/**
 * Shared worker heartbeat helper.
 */

use App\Platform\Workers\WorkerHeartbeatRepository;
use App\Support\Database;

function artsfolio_worker_heartbeat(string $workerName, string $status = 'alive', array $details = []): void
{
    $root = dirname(__DIR__, 2);

    if (!class_exists(Database::class)) {
        require_once $root . '/bootstrap/app.php';
    }

    (new WorkerHeartbeatRepository(Database::connect($root)))->beat(
        workerName: $workerName,
        hostName: gethostname() ?: 'unknown',
        processId: getmypid(),
        status: $status,
        details: $details,
    );
}

// End of file.
