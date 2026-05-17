<?php

declare(strict_types=1);

/**
 * Manual verification script for cancelled background job status.
 */

use App\Platform\Jobs\JobAdminRepository;
use App\Platform\Jobs\JobAdminService;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);

$pdo->exec(
    "INSERT INTO background_jobs (tenant_id, job_type, payload, status)
     VALUES (1, 'manual.cancel_status_test', '{\"source\":\"background_job_cancelled_status\"}', 'queued')"
);

$jobId = (int) $pdo->lastInsertId();

$service = new JobAdminService($pdo);
$service->cancel($jobId);

$jobs = (new JobAdminRepository($pdo))->latest(status: 'cancelled', jobType: 'manual.cancel_status_test', limit: 5);

$found = false;
foreach ($jobs as $job) {
    if ((int) $job['id'] === $jobId && (string) $job['status'] === 'cancelled') {
        $found = true;
    }
}

if (!$found) {
    fwrite(STDERR, "Cancelled job was not found with cancelled status.\n");
    exit(1);
}

echo json_encode([
    'job_id' => $jobId,
    'status' => 'cancelled',
    'matches' => $jobs,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
