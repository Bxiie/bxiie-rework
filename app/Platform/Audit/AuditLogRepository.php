<?php

declare(strict_types=1);

namespace App\Platform\Audit;

use PDO;

/**
 * Writes platform and tenant audit log events.
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
                tenant_id, user_id, action, entity_type, entity_id, details, ip_address
            ) VALUES (
                :tenant_id, :user_id, :action, :entity_type, :entity_id, :details, :ip_address
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
        $stmt = $this->pdo->prepare(
            "SELECT * FROM audit_log ORDER BY id DESC LIMIT :limit_count"
        );

        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

// End of file.
