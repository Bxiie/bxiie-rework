<?php

declare(strict_types=1);

namespace App\Platform\Workers;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Records and reads background worker heartbeat status.
 *
 * Heartbeat timestamps are stored and evaluated in UTC. This avoids the stale
 * worker false-positive caused by MariaDB/PHP timezone drift on hosts running
 * local time such as EDT while database timestamps are effectively UTC.
 */
final class WorkerHeartbeatRepository
{
    public const HEALTHY_AGE_SECONDS = 75;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function beat(
        string $workerName,
        ?string $hostName = null,
        ?int $processId = null,
        string $status = 'alive',
        array $details = [],
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO worker_heartbeats (
                worker_name,
                host_name,
                process_id,
                status,
                details,
                last_seen_at,
                updated_at
            ) VALUES (
                :worker_name,
                :host_name,
                :process_id,
                :status,
                :details,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )
            ON DUPLICATE KEY UPDATE
                host_name = VALUES(host_name),
                process_id = VALUES(process_id),
                status = VALUES(status),
                details = VALUES(details),
                last_seen_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()"
        );

        $stmt->execute([
            'worker_name' => $workerName,
            'host_name' => $hostName,
            'process_id' => $processId,
            'status' => $status,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
        ]);
    }

    public function latest(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM worker_heartbeats
             ORDER BY last_seen_at DESC
             LIMIT :limit_count"
        );

        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function freshestHeartbeat(): ?array
    {
        $stmt = $this->pdo->query(
            "SELECT *
             FROM worker_heartbeats
             ORDER BY last_seen_at DESC
             LIMIT 1"
        );

        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        return $row ?: null;
    }

    public function hasHealthyWorker(int $maxAgeSeconds = self::HEALTHY_AGE_SECONDS): bool
    {
        $worker = $this->freshestHeartbeat();
        if (!$worker) {
            return false;
        }

        return $this->ageSeconds((string) $worker['last_seen_at']) <= $maxAgeSeconds
            && in_array((string) ($worker['status'] ?? ''), ['alive', 'idle', 'running'], true);
    }

    public function ageSeconds(string $lastSeenAt): int
    {
        try {
            $timestamp = new DateTimeImmutable($lastSeenAt, new DateTimeZone('UTC'));
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            return max(0, $now->getTimestamp() - $timestamp->getTimestamp());
        } catch (\Throwable) {
            return 999999;
        }
    }
}

// End of file.
