<?php

declare(strict_types=1);

namespace App\Platform\Audit;

use PDO;

/**
 * Writes and reads platform and tenant audit log events.
 */
final class AuditLogRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function record(
        string $action,
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array $details = [],
        ?string $ipAddress = null,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO audit_log (
                tenant_id,
                user_id,
                action,
                entity_type,
                entity_id,
                details,
                ip_address
            ) VALUES (
                :tenant_id,
                :user_id,
                :action,
                :entity_type,
                :entity_id,
                :details,
                :ip_address
            )"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'ip_address' => $ipAddress,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function latest(int $limit = 20): array
    {
        return $this->search(limit: $limit);
    }

    public function search(
        ?string $action = null,
        ?int $tenantId = null,
        ?int $userId = null,
        int $limit = 100,
        int $offset = 0,
    ): array {
        $where = [];
        $params = [];

        if ($action !== null && $action !== '') {
            $where[] = 'action = :action';
            $params['action'] = $action;
        }

        if ($tenantId !== null) {
            $where[] = 'tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        if ($userId !== null) {
            $where[] = 'user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $sql = "SELECT * FROM audit_log";

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY id DESC LIMIT :limit_count OFFSET :offset_count";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_count', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

// End of file.
