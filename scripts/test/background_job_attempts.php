<?php

declare(strict_types=1);

/**
 * Manual verification script for background job attempt history.
 */

use App\Platform\Jobs\JobAdminRepository;
use App\Platform\Jobs\JobAttemptRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);

$pdo->exec(
    "INSERT INTO background_jobs (tenant_id, job_type, payload, status)
     VALUES (1, 'manual.job_attempt_test', '{\"source\":\"background_job_attempts\"}', 'queued')"
);

$jobId = (int) $pdo->lastInsertId();

$attempts = new JobAttemptRepository($pdo);
$attemptId = $attempts->record(
    backgroundJobId: $jobId,
    status: 'manual',
    message: 'Manual attempt history test.',
);

$job = (new JobAdminRepository($pdo))->find($jobId);
$history = $attempts->forJob($jobId);

if (!$job || !$history) {
    fwrite(STDERR, "Job attempt history test failed.\n");
    exit(1);
}

echo json_encode([
    'job_id' => $jobId,
    'attempt_id' => $attemptId,
    'job' => $job,
    'history' => $history,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
