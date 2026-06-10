<?php

declare(strict_types=1);

namespace App\Platform\Jobs\Handlers;

use PDO;

/**
 * Finalizes tenant-site provisioning jobs queued by signup.
 *
 * Signup already creates the tenant, primary platform subdomain, membership,
 * role assignment, and default CSS. This handler makes that provisioning path
 * explicit for the background worker so queued tenant.site.bootstrap jobs do
 * not fail as unknown work. It is intentionally conservative and idempotent.
 */
final class TenantSiteBootstrapJobHandler
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function handle(array $payload, ?int $tenantId = null): string
    {
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Missing tenant ID for tenant site bootstrap job.');
        }

        $hostname = $this->hostnameFromPayload($payload);

        $this->markTenantActive($tenantId);

        if ($hostname !== '') {
            $this->markPlatformSubdomainActive($tenantId, $hostname);
        }

        return json_encode([
            'tenant_id' => $tenantId,
            'hostname' => $hostname,
            'status' => 'bootstrapped',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    private function hostnameFromPayload(array $payload): string
    {
        $hostname = trim((string) ($payload['hostname'] ?? $payload['domain'] ?? ''));

        return strtolower($hostname);
    }

    private function markTenantActive(int $tenantId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tenants
             SET status = 'active', updated_at = CURRENT_TIMESTAMP
             WHERE id = :tenant_id
               AND status IN ('pending_email', 'pending_setup', 'trial', 'active')"
        );

        $stmt->execute(['tenant_id' => $tenantId]);
    }

    private function markPlatformSubdomainActive(int $tenantId, string $hostname): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tenant_domains
             SET status = 'active', updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = :tenant_id
               AND hostname = :hostname
               AND domain_type = 'platform_subdomain'
               AND status IN ('pending_dns', 'dns_verified', 'vhost_pending', 'cert_pending', 'active')"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'hostname' => $hostname,
        ]);
    }
}

// End of file.
