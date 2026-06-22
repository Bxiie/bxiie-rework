<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Platform/Jobs/BackgroundJobRepository.php' => ['FOR UPDATE SKIP LOCKED', 'requeueRunningOlderThanMinutes', "AND status = 'queued'"],
    'app/Platform/Email/EmailOutboxRepository.php' => ['FOR UPDATE SKIP LOCKED', 'requeueSendingOlderThanMinutes', "AND status = 'queued'"],
    'scripts/workers/run_once.php' => ['ARTSFOLIO_WORKER_NAME', 'ARTSFOLIO_BACKGROUND_STALE_MINUTES'],
    'scripts/workers/email_run_once.php' => ['ARTSFOLIO_WORKER_NAME', 'ARTSFOLIO_EMAIL_STALE_MINUTES'],
    'app/Platform/Jobs/JobAdminRepository.php' => ['healthSummary', 'oldest_queued_job_seconds', 'oldest_queued_email_seconds'],
    'app/Http/Controllers/Platform/Admin/JobsController.php' => ['Queue health', 'fresh_workers'],
    'scripts/systemd/artsfolio-background-worker@.service' => ['background-%i'],
    'scripts/systemd/artsfolio-email-worker@.service' => ['email-%i'],
    'scripts/ops/install_worker_services.sh' => ['disable --now artsfolio-background-worker.service'],
    'database/migrations/0040_worker_scaling_indexes.sql' => ['idx_background_jobs_status_updated', 'idx_email_outbox_status_updated'],
];
foreach ($checks as $file => $needles) {
    $content = file_get_contents($root . '/' . $file);
    if ($content === false) { fwrite(STDERR, "Missing {$file}\n"); exit(1); }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) { fwrite(STDERR, "Missing {$needle} in {$file}\n"); exit(1); }
    }
}
echo "Phase 4 worker scaling static checks passed.\n";

// End of file.
