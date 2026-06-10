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

        $tenant = $this->resolveActiveDomain($hostname);
        if ($tenant !== null) {
            return $tenant;
        }

        return $this->resolvePlatformSubdomainFallback($hostname);
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

    private function resolveActiveDomain(string $hostname): ?TenantContext
    {
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
               AND t.status IN ('trial', 'active')
             LIMIT 1"
        );

        $stmt->execute(['hostname' => $hostname]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->contextFromRow($row) : null;
    }

    /**
     * Platform subdomains are routing affordances. Resolve slug.artsfol.io from
     * the tenant slug even if tenant_domains was not seeded or was left in a
     * non-active DNS state. Custom domains still require active tenant_domains.
     */
    private function resolvePlatformSubdomainFallback(string $hostname): ?TenantContext
    {
        if (!str_ends_with($hostname, $this->platformWildcardSuffix)) {
            return null;
        }

        $slug = substr($hostname, 0, -strlen($this->platformWildcardSuffix));
        if ($slug === '' || str_contains($slug, '.') || in_array($slug, ['www', 'app', 'admin', 'api'], true)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                t.id AS tenant_id,
                t.uuid AS tenant_uuid,
                t.slug,
                t.name,
                t.status,
                :hostname AS hostname,
                'platform_subdomain' AS domain_type,
                0 AS is_primary
             FROM tenants t
             WHERE t.slug = :slug
               AND t.status IN ('trial', 'active')
             LIMIT 1"
        );

        $stmt->execute([
            'hostname' => $hostname,
            'slug' => $slug,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->contextFromRow($row) : null;
    }

    private function contextFromRow(array $row): TenantContext
    {
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
