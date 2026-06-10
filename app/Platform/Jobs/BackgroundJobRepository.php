<?php

declare(strict_types=1);

namespace App\Platform\Jobs;

use PDO;

/**
 * Handles persistence for platform background jobs.
 */
final class BackgroundJobRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function enqueue(
        string $jobType,
        array $payload = [],
        ?int $tenantId = null,
        int $availableAfterSeconds = 0,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO background_jobs (
                tenant_id,
                job_type,
                payload,
                status,
                attempts,
                available_at,
                created_at
            ) VALUES (
                :tenant_id,
                :job_type,
                :payload,
                'queued',
                0,
                DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :available_after SECOND),
                CURRENT_TIMESTAMP
            )"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'job_type' => $jobType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'available_after' => $availableAfterSeconds,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function claimNext(): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->query(
                "SELECT *
                 FROM background_jobs
                 WHERE status = 'queued'
                   AND available_at <= CURRENT_TIMESTAMP
                 ORDER BY available_at ASC, id ASC
                 LIMIT 1
                 FOR UPDATE"
            );

            $job = $stmt->fetch();

            if (!$job) {
                $this->pdo->commit();
                return null;
            }

            $update = $this->pdo->prepare(
                "UPDATE background_jobs
                 SET status = 'running',
                     attempts = attempts + 1,
                     started_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );

            $update->execute(['id' => $job['id']]);

            $this->pdo->commit();

            $job['payload'] = $job['payload']
                ? json_decode((string) $job['payload'], true, 512, JSON_THROW_ON_ERROR)
                : [];

            return $job;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function markComplete(int $jobId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE background_jobs
             SET status = 'complete',
                 completed_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        $stmt->execute(['id' => $jobId]);
    }

    public function markFailed(int $jobId, string $errorMessage): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE background_jobs
             SET status = 'failed',
                 last_error = :last_error,
                 failed_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        $stmt->execute([
            'id' => $jobId,
            'last_error' => $errorMessage,
        ]);
    }
}

// End of file.
