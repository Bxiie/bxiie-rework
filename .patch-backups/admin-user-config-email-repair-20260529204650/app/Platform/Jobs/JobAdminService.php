<?php

declare(strict_types=1);

namespace App\Platform\Jobs;

use PDO;

/**
 * Coordinates platform-admin background job maintenance actions.
 */
final class JobAdminService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?JobAttemptRepository $attempts = null,
    ) {
    }

    public function requeue(int $jobId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE background_jobs
             SET status = 'queued',
                 attempts = 0,
                 last_error = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        $stmt->execute(['id' => $jobId]);

        $this->attempts?->record(
            backgroundJobId: $jobId,
            status: 'admin_requeued',
            message: 'Job requeued by platform admin.',
            finishedAt: date('Y-m-d H:i:s'),
        );
    }

    public function cancel(int $jobId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE background_jobs
             SET status = 'cancelled',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND status = 'queued'"
        );

        $stmt->execute(['id' => $jobId]);

        $this->attempts?->record(
            backgroundJobId: $jobId,
            status: 'admin_cancelled',
            message: 'Queued job cancelled by platform admin.',
            finishedAt: date('Y-m-d H:i:s'),
        );
    }
}

// End of file.
