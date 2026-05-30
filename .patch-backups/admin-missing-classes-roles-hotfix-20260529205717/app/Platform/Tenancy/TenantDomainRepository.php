<?php

declare(strict_types=1);

namespace App\Platform\Tenancy;

use PDO;

/**
 * Handles tenant domain persistence for platform subdomains and paid custom domains.
 */
final class TenantDomainRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function addDomain(
        int $tenantId,
        string $hostname,
        string $domainType,
        string $status = 'pending_dns',
        bool $isPrimary = false,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_domains (
                tenant_id,
                hostname,
                domain_type,
                status,
                is_primary
            ) VALUES (
                :tenant_id,
                :hostname,
                :domain_type,
                :status,
                :is_primary
            )
            ON DUPLICATE KEY UPDATE
                domain_type = VALUES(domain_type),
                status = VALUES(status),
                is_primary = VALUES(is_primary),
                updated_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'hostname' => $this->normalizeHostname($hostname),
            'domain_type' => $domainType,
            'status' => $status,
            'is_primary' => $isPrimary ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setStatus(string $hostname, string $status): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tenant_domains
             SET status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE hostname = :hostname"
        );

        $stmt->execute([
            'hostname' => $this->normalizeHostname($hostname),
            'status' => $status,
        ]);
    }

    public function setPrimary(int $tenantId, string $hostname): void
    {
        $hostname = $this->normalizeHostname($hostname);

        $this->pdo->beginTransaction();

        try {
            $clear = $this->pdo->prepare(
                "UPDATE tenant_domains
                 SET is_primary = 0,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = :tenant_id"
            );

            $clear->execute(['tenant_id' => $tenantId]);

            $set = $this->pdo->prepare(
                "UPDATE tenant_domains
                 SET is_primary = 1,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = :tenant_id
                   AND hostname = :hostname"
            );

            $set->execute([
                'tenant_id' => $tenantId,
                'hostname' => $hostname,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function listForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, tenant_id, hostname, domain_type, status, is_primary, created_at, updated_at
             FROM tenant_domains
             WHERE tenant_id = :tenant_id
             ORDER BY is_primary DESC, hostname"
        );

        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    private function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(trim($hostname));

        if (str_contains($hostname, ':')) {
            $hostname = explode(':', $hostname, 2)[0];
        }

        return rtrim($hostname, '.');
    }
}

// End of file.
