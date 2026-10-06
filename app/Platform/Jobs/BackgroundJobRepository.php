<?php

declare(strict_types=1);

namespace App\Platform\Jobs;

use PDO;

/**
 * Handles persistence for platform background jobs.
 */
final class BackgroundJobRepository
{
    /**
     * Default retry budget for a job that doesn't specify its own, used when
     * ARTSFOLIO_BACKGROUND_MAX_ATTEMPTS is also unset. One "attempt" is the
     * initial run, so 5 means up to 4 automatic retries after a failure.
     */
    private const DEFAULT_MAX_ATTEMPTS = 5;

    /**
     * Longest gap between retries, regardless of how many attempts remain.
     */
    private const MAX_RETRY_DELAY_SECONDS = 1800;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function enqueue(
        string $jobType,
        array $payload = [],
        ?int $tenantId = null,
        int $availableAfterSeconds = 0,
        ?int $maxAttempts = null,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO background_jobs (
                tenant_id,
                job_type,
                payload,
                status,
                attempts,
                max_attempts,
                available_at,
                created_at
            ) VALUES (
                :tenant_id,
                :job_type,
                :payload,
                'queued',
                0,
                :max_attempts,
                DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :available_after SECOND),
                CURRENT_TIMESTAMP
            )"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'job_type' => $jobType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'max_attempts' => self::resolveMaxAttempts($maxAttempts),
            'available_after' => $availableAfterSeconds,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Resolves the retry budget for a newly enqueued job: an explicit
     * per-call value, else the operator-configured default, else the
     * built-in default. Matches the getenv()-with-in-code-default pattern
     * already used for this worker's other ops knobs (e.g.
     * ARTSFOLIO_BACKGROUND_STALE_MINUTES in scripts/workers/run_once.php).
     */
    private static function resolveMaxAttempts(?int $maxAttempts): int
    {
        if ($maxAttempts !== null) {
            return max(1, $maxAttempts);
        }

        return max(1, (int) (getenv('ARTSFOLIO_BACKGROUND_MAX_ATTEMPTS') ?: self::DEFAULT_MAX_ATTEMPTS));
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
        ?int $maxAttempts = null,
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

            return $this->enqueue($jobType, $payload, $tenantId, $availableAfterSeconds, $maxAttempts);
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

    /**
     * Records a failed attempt. While attempts remain under the job's
     * max_attempts budget, the job goes back to 'queued' with a backed-off
     * available_at instead of being marked terminally 'failed' — so a
     * transient error (network blip, deadlock, etc.) doesn't permanently
     * stop the job or count toward the queue.jobs.failed health alert
     * (app/Platform/Monitoring/OperationsMonitor.php), which only counts
     * status = 'failed' rows.
     *
     * @return bool true when the job is now terminally failed (retries
     *              exhausted), false when it was requeued for another try.
     */
    public function markFailed(int $jobId, string $errorMessage): bool
    {
        $current = $this->pdo->prepare(
            "SELECT attempts, max_attempts
             FROM background_jobs
             WHERE id = :id
               AND status = 'running'
             LIMIT 1"
        );
        $current->execute(['id' => $jobId]);
        $row = $current->fetch();

        // Nothing to transition — e.g. another process already recovered
        // this job (requeueRunningOlderThanMinutes). Treat as terminal so
        // the caller doesn't report a misleading "retry scheduled".
        if (!$row) {
            return true;
        }

        $attempts = (int) $row['attempts'];
        $maxAttempts = max(1, (int) $row['max_attempts']);

        if ($attempts < $maxAttempts) {
            $stmt = $this->pdo->prepare(
                "UPDATE background_jobs
                 SET status = 'queued',
                     started_at = NULL,
                     available_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :delay_seconds SECOND),
                     last_error = :last_error,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND status = 'running'"
            );
            $stmt->execute([
                'id' => $jobId,
                'last_error' => $errorMessage,
                'delay_seconds' => $this->retryDelaySeconds($attempts),
            ]);

            return false;
        }

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

        return true;
    }

    /**
     * Exponential backoff (60s, 120s, 240s, ...) capped at
     * MAX_RETRY_DELAY_SECONDS, keyed off how many attempts have already run.
     */
    private function retryDelaySeconds(int $attemptsSoFar): int
    {
        $delay = 60 * (2 ** max(0, $attemptsSoFar - 1));

        return min($delay, self::MAX_RETRY_DELAY_SECONDS);
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
