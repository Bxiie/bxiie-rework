<?php

/**
 * Platform tenant-management repository.
 */

declare(strict_types=1);

namespace App\Platform\Tenants;

use App\Platform\Directory\TenantDirectoryProfileRepository;
use PDO;

/**
 * Read/write repository for platform-admin tenant management screens.
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
             WHERE t.status <> 'deleted'
             GROUP BY t.id, t.uuid, t.slug, t.name, t.status, t.created_at
             ORDER BY t.id DESC
             LIMIT :limit_count"
        );

        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function setStatus(int $tenantId, string $status): void
    {
        if (!in_array($status, ['trial', 'active', 'suspended', 'archived', 'deleted'], true)) {
            throw new \InvalidArgumentException('Invalid tenant status.');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE tenants
             SET status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :tenant_id"
        );
        $stmt->execute(['status' => $status, 'tenant_id' => $tenantId]);
        (new TenantDirectoryProfileRepository($this->pdo))->syncTenant($tenantId);
    }

    /**
     * Returns the preferred artsfol.io subdomain for a tenant.
     */
    public function subdomainForTenant(int $tenantId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT hostname FROM tenant_domains WHERE tenant_id = :tenant_id AND hostname LIKE '%.artsfol.io' ORDER BY is_primary DESC, id ASC LIMIT 1");
        $stmt->execute(['tenant_id' => $tenantId]);
        $host = $stmt->fetchColumn();
        return $host ? (string) $host : null;
    }


    /**
     * Returns the best public URL for opening the tenant site from platform admin.
     */
    public function publicUrlForTenant(int $tenantId): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT hostname
             FROM tenant_domains
             WHERE tenant_id = :tenant_id
               AND status <> 'disabled'
             ORDER BY
                CASE WHEN hostname LIKE '%.artsfol.io' THEN 0 ELSE 1 END,
                is_primary DESC,
                id ASC
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $host = $stmt->fetchColumn();
        if (!$host) {
            return null;
        }

        return 'https://' . (string) $host . '/';
    }

    /**
     * Suspends tenant content without deleting data.
     */
    public function suspendTenant(int $tenantId): void
    {
        $stmt = $this->pdo->prepare("UPDATE tenants SET status = 'suspended', suspended_at = CURRENT_TIMESTAMP WHERE id = :tenant_id");
        $stmt->execute(['tenant_id' => $tenantId]);
        (new TenantDirectoryProfileRepository($this->pdo))->syncTenant($tenantId);
    }

    /**
     * Soft-deletes a tenant.
     */
    public function deleteTenant(int $tenantId): void
    {
        $this->pdo->beginTransaction();

        try {
            $domains = $this->pdo->prepare('DELETE FROM tenant_domains WHERE tenant_id = :tenant_id');
            $domains->execute(['tenant_id' => $tenantId]);

            $stmt = $this->pdo->prepare("UPDATE tenants SET status = 'deleted', deleted_at = CURRENT_TIMESTAMP WHERE id = :tenant_id");
            $stmt->execute(['tenant_id' => $tenantId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

// End of file.
