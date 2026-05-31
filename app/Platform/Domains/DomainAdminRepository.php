<?php

declare(strict_types=1);

namespace App\Platform\Domains;

use PDO;

/**
 * Read-side repository for platform-admin custom domain screens.
 */
final class DomainAdminRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function latest(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                td.id,
                td.tenant_id,
                t.slug AS tenant_slug,
                t.name AS tenant_name,
                td.hostname,
                td.status,
                td.created_at,
                td.updated_at,
                td.dns_last_checked_at,
                td.dns_last_result,
                td.dns_last_error
             FROM tenant_domains td
             JOIN tenants t ON t.id = td.tenant_id
             ORDER BY td.id DESC
             LIMIT :limit_count OFFSET :offset_count"
        );

        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_count', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

// End of file.
