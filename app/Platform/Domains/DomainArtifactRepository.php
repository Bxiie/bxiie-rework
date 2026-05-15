<?php

declare(strict_types=1);

namespace App\Platform\Domains;

use PDO;

/**
 * Persists generated domain automation artifacts for inspection and later approval.
 */
final class DomainArtifactRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(
        int $tenantId,
        string $hostname,
        string $artifactType,
        string $artifactBody,
        string $status = 'rendered',
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO domain_artifacts (
                tenant_id,
                hostname,
                artifact_type,
                artifact_body,
                status
            ) VALUES (
                :tenant_id,
                :hostname,
                :artifact_type,
                :artifact_body,
                :status
            )"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'hostname' => strtolower(trim($hostname)),
            'artifact_type' => $artifactType,
            'artifact_body' => $artifactBody,
            'status' => $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function latestForHostname(string $hostname): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM domain_artifacts
             WHERE hostname = :hostname
             ORDER BY id DESC
             LIMIT 1"
        );

        $stmt->execute(['hostname' => strtolower(trim($hostname))]);

        $row = $stmt->fetch();

        return $row ?: null;
    }
}

// End of file.
