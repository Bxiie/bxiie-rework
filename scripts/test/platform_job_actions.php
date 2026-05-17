<?php

declare(strict_types=1);

/**
 * Manual verification script for platform background job actions.
 */

use App\Platform\Jobs\JobAdminRepository;
use App\Platform\Jobs\JobAdminService;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$pdo->exec(
    "INSERT INTO background_jobs (tenant_id, job_type, payload, status, attempts, last_error)
     VALUES (1, 'manual.test_job_action', '{\"source\":\"platform_job_actions\"}', 'failed', 3, 'manual failure')"
);

$jobId = (int) $pdo->lastInsertId();
$service = new JobAdminService($pdo);
$service->requeue($jobId);
$service->cancel($jobId);

$jobs = (new JobAdminRepository($pdo))->latest(jobType: 'manual.test_job_action', limit: 5);

echo json_encode([
    'job_id' => $jobId,
    'jobs' => $jobs,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
