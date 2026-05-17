<?php

declare(strict_types=1);

/**
 * Manual verification script for platform background job detail repository access.
 */

use App\Platform\Jobs\JobAdminRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$pdo->exec(
    "INSERT INTO background_jobs (tenant_id, job_type, payload, status)
     VALUES (1, 'manual.job_detail_test', '{\"source\":\"platform_job_detail\"}', 'queued')"
);

$jobId = (int) $pdo->lastInsertId();
$job = (new JobAdminRepository($pdo))->find($jobId);

if (!$job || (int) $job['id'] !== $jobId) {
    fwrite(STDERR, "Could not find inserted job.\n");
    exit(1);
}

echo json_encode([
    'job_id' => $jobId,
    'job' => $job,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
