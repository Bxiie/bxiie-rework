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


    /**
     * Stores the most recent DNS verification result for platform-admin review.
     */
    public function recordDnsVerificationResult(string $hostname, array $result, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tenant_domains
             SET dns_last_checked_at = UTC_TIMESTAMP(),
                 dns_last_result = :dns_last_result,
                 dns_last_error = :dns_last_error,
                 updated_at = CURRENT_TIMESTAMP
             WHERE hostname = :hostname"
        );

        $stmt->execute([
            'hostname' => $this->normalizeHostname($hostname),
            'dns_last_result' => json_encode($result, JSON_THROW_ON_ERROR),
            'dns_last_error' => $error,
        ]);
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

    /**
     * Delete a tenant-owned custom domain by id.
     */
    public function deleteDomain(int $tenantId, int $domainId, bool $allowSubdomain = false): void
    {
        $extra = $allowSubdomain ? '' : " AND domain_type <> 'subdomain' AND hostname NOT LIKE '%.artsfol.io'";
        $stmt = $this->pdo->prepare(
            "DELETE FROM tenant_domains
             WHERE tenant_id = :tenant_id
               AND id = :id{$extra}"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $domainId]);
    }

    /**
     * Delete all domains for a tenant so slug and hostnames can be reused.
     */
    public function deleteAllForTenant(int $tenantId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tenant_domains WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $tenantId]);
    }

    /**
     * Count billable custom-domain groups.
     */
    public function billableCustomDomainCount(int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT hostname
             FROM tenant_domains
             WHERE tenant_id = :tenant_id
               AND domain_type <> 'subdomain'
               AND hostname NOT LIKE '%.artsfol.io'"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $groups = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $hostname) {
            $hostname = $this->normalizeHostname((string) $hostname);
            $groups[preg_replace('/^www\./', '', $hostname)] = true;
        }

        return count($groups);
    }

    /**
     * Returns true when this hostname would create a new billable custom-domain group.
     */
    public function isNewBillableCustomDomainGroup(int $tenantId, string $hostname): bool
    {
        $hostname = preg_replace('/^www\./', '', $this->normalizeHostname($hostname));
        $stmt = $this->pdo->prepare(
            "SELECT hostname
             FROM tenant_domains
             WHERE tenant_id = :tenant_id
               AND domain_type <> 'subdomain'
               AND hostname NOT LIKE '%.artsfol.io'"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $existing) {
            if (preg_replace('/^www\./', '', $this->normalizeHostname((string) $existing)) === $hostname) {
                return false;
            }
        }

        return true;
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
