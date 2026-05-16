<?php

declare(strict_types=1);

namespace App\Tenant\Signup;

use App\Platform\Tenancy\TenantContext;
use PDO;

/**
 * Persists tenant public email-list signups.
 */
final class EmailSignupRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function upsert(
        TenantContext $tenant,
        string $email,
        ?string $name = null,
        ?string $source = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        string $consentStatus = 'pending',
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO email_signups (
                tenant_id,
                email,
                name,
                source,
                ip_address,
                user_agent,
                consent_status,
                updated_at
            ) VALUES (
                :tenant_id,
                :email,
                :name,
                :source,
                :ip_address,
                :user_agent,
                :consent_status,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                source = VALUES(source),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                consent_status = VALUES(consent_status),
                updated_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'email' => strtolower(trim($email)),
            'name' => $name ? trim($name) : null,
            'source' => $source,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'consent_status' => $consentStatus,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function latestForTenant(TenantContext $tenant, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM email_signups
             WHERE tenant_id = :tenant_id
             ORDER BY id DESC
             LIMIT :limit_count"
        );

        $stmt->bindValue('tenant_id', $tenant->tenantId, PDO::PARAM_INT);
        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

// End of file.
