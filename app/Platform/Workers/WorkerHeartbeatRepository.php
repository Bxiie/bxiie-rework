<?php

declare(strict_types=1);

namespace App\Platform\Workers;

use PDO;

/**
 * Records and reads background worker heartbeat status.
 */
final class WorkerHeartbeatRepository
{
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
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                host_name = VALUES(host_name),
                process_id = VALUES(process_id),
                status = VALUES(status),
                details = VALUES(details),
                last_seen_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP"
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
}

// End of file.
