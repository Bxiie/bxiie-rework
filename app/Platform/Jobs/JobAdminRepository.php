<?php

declare(strict_types=1);

namespace App\Platform\Jobs;

use PDO;

/**
 * Read-side repository for platform-admin background job screens.
 */
final class JobAdminRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function find(int $jobId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                bj.id,
                bj.tenant_id,
                t.slug AS tenant_slug,
                bj.job_type,
                bj.status,
                bj.attempts,
                bj.payload,
                bj.last_error,
                bj.created_at,
                bj.updated_at,
                MIN(bja.started_at) AS first_started_at,
                MAX(bja.finished_at) AS last_finished_at,
                MAX(bja.created_at) AS last_attempt_at,
                COUNT(bja.id) AS attempt_history_count
             FROM background_jobs bj
             LEFT JOIN background_job_attempts bja ON bja.background_job_id = bj.id
             LEFT JOIN tenants t ON t.id = bj.tenant_id
             WHERE bj.id = :id
             GROUP BY bj.id, bj.tenant_id, t.slug, bj.job_type, bj.status, bj.attempts, bj.payload, bj.last_error, bj.created_at, bj.updated_at
             LIMIT 1"
        );

        $stmt->execute(['id' => $jobId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function latest(?string $status = null, ?string $jobType = null, int $limit = 100, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[] = 'bj.status = :status';
            $params['status'] = $status;
        }

        if ($jobType !== null && $jobType !== '') {
            $where[] = 'bj.job_type = :job_type';
            $params['job_type'] = $jobType;
        }

        $sql = "SELECT
                bj.id,
                bj.tenant_id,
                t.slug AS tenant_slug,
                bj.job_type,
                bj.status,
                bj.attempts,
                bj.payload,
                bj.last_error,
                bj.created_at,
                bj.updated_at,
                MIN(bja.started_at) AS first_started_at,
                MAX(bja.finished_at) AS last_finished_at,
                MAX(bja.created_at) AS last_attempt_at,
                COUNT(bja.id) AS attempt_history_count
             FROM background_jobs bj
             LEFT JOIN tenants t ON t.id = bj.tenant_id
             LEFT JOIN background_job_attempts bja ON bja.background_job_id = bj.id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ($where ? '' : '') . ' GROUP BY bj.id, bj.tenant_id, t.slug, bj.job_type, bj.status, bj.attempts, bj.payload, bj.last_error, bj.created_at, bj.updated_at ORDER BY bj.id DESC LIMIT :limit_count OFFSET :offset_count';

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_count', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

// End of file.
