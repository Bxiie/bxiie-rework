<?php

declare(strict_types=1);

namespace App\Platform\Jobs;

use PDO;

/**
 * Records and reads background job attempt history.
 */
final class JobAttemptRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function record(
        int $backgroundJobId,
        string $status,
        ?string $message = null,
        ?string $startedAt = null,
        ?string $finishedAt = null,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO background_job_attempts (
                background_job_id,
                status,
                message,
                started_at,
                finished_at
            ) VALUES (
                :background_job_id,
                :status,
                :message,
                :started_at,
                :finished_at
            )"
        );

        $stmt->execute([
            'background_job_id' => $backgroundJobId,
            'status' => $status,
            'message' => $message,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function forJob(int $backgroundJobId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM background_job_attempts
             WHERE background_job_id = :background_job_id
             ORDER BY id DESC"
        );

        $stmt->execute(['background_job_id' => $backgroundJobId]);

        return $stmt->fetchAll();
    }
}

// End of file.
