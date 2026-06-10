<?php

declare(strict_types=1);

/**
 * Manual verification script for background job action attempt-history records.
 */

use App\Platform\Jobs\JobAdminService;
use App\Platform\Jobs\JobAttemptRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);

$pdo->exec(
    "INSERT INTO background_jobs (tenant_id, job_type, payload, status)
     VALUES (1, 'manual.job_action_attempts', '{\"source\":\"platform_job_action_attempts\"}', 'queued')"
);

$jobId = (int) $pdo->lastInsertId();
$attempts = new JobAttemptRepository($pdo);
$service = new JobAdminService($pdo, $attempts);

$service->cancel($jobId);
$service->requeue($jobId);

$history = $attempts->forJob($jobId);
$statuses = array_map(static fn (array $row): string => (string) $row['status'], $history);

if (!in_array('admin_cancelled', $statuses, true) || !in_array('admin_requeued', $statuses, true)) {
    fwrite(STDERR, "Expected admin_cancelled and admin_requeued attempt history rows.\n");
    exit(1);
}

echo json_encode([
    'job_id' => $jobId,
    'statuses' => $statuses,
    'history' => $history,
], JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
