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
