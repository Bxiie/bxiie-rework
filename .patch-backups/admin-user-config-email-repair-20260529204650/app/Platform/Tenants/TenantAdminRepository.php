<?php

declare(strict_types=1);

namespace App\Platform\Tenants;

use PDO;

/**
 * Read-side repository for platform-admin tenant management screens.
 */
final class TenantAdminRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function latest(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                t.id,
                t.uuid,
                t.slug,
                t.name,
                t.status,
                t.created_at,
                COUNT(td.id) AS domain_count
             FROM tenants t
             LEFT JOIN tenant_domains td ON td.tenant_id = t.id
             GROUP BY t.id, t.uuid, t.slug, t.name, t.status, t.created_at
             ORDER BY t.id DESC
             LIMIT :limit_count"
        );

        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

// End of file.
