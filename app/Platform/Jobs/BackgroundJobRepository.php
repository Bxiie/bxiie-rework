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

    /**
     * Enqueues at most one queued or running job for a singleton job type.
     *
     * A MariaDB advisory lock makes the check-and-insert atomic across worker
     * processes without requiring a schema-level uniqueness constraint.
     */
    public function enqueueSingleton(
        string $jobType,
        array $payload = [],
        ?int $tenantId = null,
        int $availableAfterSeconds = 0,
        ?int $excludeJobId = null,
    ): int {
        $lockName = 'artsfolio-singleton:' . hash('sha1', $jobType);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, 5)');
        $lock->execute(['lock_name' => $lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new \RuntimeException("Unable to acquire singleton job lock for {$jobType}.");
        }

        try {
            $existing = $this->pdo->prepare(
                "SELECT id
                 FROM background_jobs
                 WHERE job_type = :job_type
                   AND status IN ('queued', 'running')
                   AND (:exclude_job_id IS NULL OR id <> :exclude_job_id)
                 ORDER BY id ASC
                 LIMIT 1"
            );
            $existing->execute([
                'job_type' => $jobType,
                'exclude_job_id' => $excludeJobId,
            ]);
            $existingId = $existing->fetchColumn();
            if ($existingId !== false) {
                return (int) $existingId;
            }

            return $this->enqueue($jobType, $payload, $tenantId, $availableAfterSeconds);
        } finally {
            $release = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $release->execute(['lock_name' => $lockName]);
        }
    }

    public function claimNext(): ?array
    {
        $claimedJobId = null;
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->query(
                "SELECT *
                 FROM background_jobs
                 WHERE status = 'queued'
                   AND available_at <= CURRENT_TIMESTAMP
                 ORDER BY available_at ASC, id ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED"
            );

            $job = $stmt->fetch();

            if (!$job) {
                $this->pdo->commit();
                return null;
            }

            $claimedJobId = (int) $job['id'];
            $executionLockName = $this->executionLockName($claimedJobId);
            $executionLock = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
            $executionLock->execute(['lock_name' => $executionLockName]);
            if ((int) $executionLock->fetchColumn() !== 1) {
                $this->pdo->rollBack();
                return null;
            }

            $update = $this->pdo->prepare(
                "UPDATE background_jobs
                 SET status = 'running',
                     attempts = attempts + 1,
                     started_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND status = 'queued'"
            );

            $update->execute(['id' => $job['id']]);

            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                $this->releaseExecutionLock((int) $job['id']);
                return null;
            }

            $this->pdo->commit();

            $job['payload'] = $job['payload']
                ? json_decode((string) $job['payload'], true, 512, JSON_THROW_ON_ERROR)
                : [];

            return $job;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($claimedJobId !== null) {
                $this->releaseExecutionLock($claimedJobId);
            }
            throw $e;
        }
    }

    /**
     * Requeue jobs abandoned by a terminated worker.
     */
    public function requeueRunningOlderThanMinutes(int $minutes): int
    {
        $minutes = max(1, $minutes);
        $stmt = $this->pdo->prepare(
            "UPDATE background_jobs
             SET status = 'queued',
                 started_at = NULL,
                 last_error = CONCAT(
                     COALESCE(NULLIF(last_error, ''), ''),
                     CASE WHEN COALESCE(last_error, '') = '' THEN '' ELSE '\n' END,
                     'Recovered stale running job at ', CURRENT_TIMESTAMP, '.'
                 ),
                 updated_at = CURRENT_TIMESTAMP
             WHERE status = 'running'
               AND COALESCE(started_at, updated_at) < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL :minutes MINUTE)
               AND IS_FREE_LOCK(CONCAT('artsfolio-background-job:', id)) = 1"
        );
        $stmt->execute(['minutes' => $minutes]);
        return $stmt->rowCount();
    }

    public function markComplete(int $jobId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE background_jobs
             SET status = 'complete',
                 completed_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND status = 'running'"
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
             WHERE id = :id
               AND status = 'running'"
        );

        $stmt->execute([
            'id' => $jobId,
            'last_error' => $errorMessage,
        ]);
    }

    public function releaseExecutionLock(int $jobId): void
    {
        $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => $this->executionLockName($jobId)]);
    }

    private function executionLockName(int $jobId): string
    {
        return 'artsfolio-background-job:' . $jobId;
    }
}

// End of file.
