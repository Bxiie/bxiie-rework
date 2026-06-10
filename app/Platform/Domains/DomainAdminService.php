<?php

declare(strict_types=1);

namespace App\Platform\Domains;

use PDO;

/**
 * Coordinates platform-admin custom domain maintenance actions.
 */
final class DomainAdminService
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function queueDnsVerification(int $domainId): int
    {
        $domain = $this->findDomain($domainId);

        if (!$domain) {
            throw new \RuntimeException("Domain not found: {$domainId}");
        }

        return $this->queueJob(
            tenantId: (int) $domain['tenant_id'],
            jobType: 'custom_domain.verify_dns',
            payload: ['hostname' => (string) $domain['hostname']],
        );
    }

    public function queueVhostRender(int $domainId, string $documentRoot): int
    {
        $domain = $this->findDomain($domainId);

        if (!$domain) {
            throw new \RuntimeException("Domain not found: {$domainId}");
        }

        return $this->queueJob(
            tenantId: (int) $domain['tenant_id'],
            jobType: 'custom_domain.render_vhost',
            payload: [
                'hostname' => (string) $domain['hostname'],
                'document_root' => $documentRoot,
            ],
        );
    }

    /**
     * Add a custom domain for a tenant when the current plan allows another billable group.
     */
    public function addCustomDomain(int $tenantId, string $hostname, bool $skipPlanCheck = false): int
    {
        $hostname = $this->normalizeHostname($hostname);
        if ($hostname === '' || str_ends_with($hostname, '.artsfol.io')) {
            throw new \RuntimeException('Enter a non-ArtsFolio custom hostname.');
        }

        if (!$skipPlanCheck && !$this->planAllowsDomain($tenantId, $hostname)) {
            throw new \RuntimeException('The tenant plan does not allow another custom domain.');
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_domains (tenant_id, hostname, domain_type, status, is_primary)
             VALUES (:tenant_id, :hostname, 'custom', 'pending_dns', 0)"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'hostname' => $hostname]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Delete a domain row. Used by platform admins.
     */
    public function deleteDomain(int $domainId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tenant_domains WHERE id = :id');
        $stmt->execute(['id' => $domainId]);
    }

    /**
     * Plan check that ignores default artsfol.io and treats www/apex as one group.
     */
    private function planAllowsDomain(int $tenantId, string $hostname): bool
    {
        $plan = $this->currentPlan($tenantId);
        $allowed = (int) ($plan['custom_domain_included'] ?? 0);
        if ($allowed < 1) {
            return false;
        }

        $newGroup = preg_replace('/^www\./', '', $hostname);
        $groups = [];
        $stmt = $this->pdo->prepare(
            "SELECT hostname
             FROM tenant_domains
             WHERE tenant_id = :tenant_id
               AND domain_type <> 'subdomain'
               AND hostname NOT LIKE '%.artsfol.io'"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $existing) {
            $groups[preg_replace('/^www\./', '', $this->normalizeHostname((string) $existing))] = true;
        }

        if (isset($groups[$newGroup])) {
            return true;
        }

        return count($groups) < $allowed;
    }

    private function currentPlan(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*
             FROM tenant_plan_assignments tpa
             JOIN plans p ON p.id = tpa.plan_id
             WHERE tpa.tenant_id = :tenant_id
             ORDER BY tpa.id DESC
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $plan = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $plan ?: null;
    }

    private function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(trim($hostname));
        $hostname = preg_replace('#^https?://#', '', $hostname) ?? $hostname;
        $hostname = explode('/', $hostname, 2)[0];
        $hostname = explode(':', $hostname, 2)[0];

        return rtrim($hostname, '.');
    }

    private function findDomain(int $domainId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM tenant_domains
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $domainId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function queueJob(int $tenantId, string $jobType, array $payload): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO background_jobs (
                tenant_id,
                job_type,
                payload,
                status
            ) VALUES (
                :tenant_id,
                :job_type,
                :payload,
                'queued'
            )"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'job_type' => $jobType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}

// End of file.
