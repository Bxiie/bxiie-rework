<?php

/**
 * Tenant host resolver.
 */

declare(strict_types=1);

namespace App\Platform\Tenancy;

use PDO;

/**
 * Resolves the active tenant from the HTTP Host header.
 */
final class TenantResolver
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $platformHost = 'artsfol.io',
        private readonly string $adminHost = 'app.artsfol.io',
        private readonly string $platformWildcardSuffix = '.artsfol.io',
    ) {
    }

    public function resolveFromHost(string $host): ?TenantContext
    {
        $hostname = $this->normalizeHost($host);

        if ($hostname === $this->platformHost || $hostname === $this->adminHost) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                t.id AS tenant_id,
                t.uuid AS tenant_uuid,
                t.slug,
                t.name,
                t.status,
                d.hostname,
                d.domain_type,
                d.is_primary
             FROM tenant_domains d
             JOIN tenants t ON t.id = d.tenant_id
             WHERE d.hostname = :hostname
               AND d.status = 'active'
               AND t.status <> 'deleted'
             LIMIT 1"
        );

        $stmt->execute(['hostname' => $hostname]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new TenantContext(
            tenantId: (int) $row['tenant_id'],
            tenantUuid: (string) $row['tenant_uuid'],
            slug: (string) $row['slug'],
            name: (string) $row['name'],
            hostname: (string) $row['hostname'],
            domainType: (string) $row['domain_type'],
            isPrimaryDomain: (bool) $row['is_primary'],
            status: (string) $row['status'],
        );
    }

    public function suspendedTenantForHost(string $host): ?array
    {
        $hostname = $this->normalizeHost($host);

        $stmt = $this->pdo->prepare(
            "SELECT t.id, t.slug, t.name, t.status, d.hostname
             FROM tenant_domains d
             JOIN tenants t ON t.id = d.tenant_id
             WHERE d.hostname = :hostname
               AND d.status = 'active'
               AND t.status IN ('suspended', 'archived')
             LIMIT 1"
        );
        $stmt->execute(['hostname' => $hostname]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        return rtrim($host, '.');
    }
}

// End of file.
