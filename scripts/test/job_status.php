<?php

declare(strict_types=1);

/**
 * Prints recent background jobs for manual development verification.
 */

use App\Support\Database;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);

$stmt = $pdo->query(
    "SELECT id, tenant_id, job_type, status, attempts, payload, completed_at, failed_at, last_error
     FROM background_jobs
     ORDER BY id DESC
     LIMIT 20"
);

echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
