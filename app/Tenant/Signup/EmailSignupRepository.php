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
        ?string $country = null,
        ?string $region = null,
        ?string $city = null,
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
                country,
                region,
                city,
                consent_status,
                updated_at
            ) VALUES (
                :tenant_id,
                :email,
                :name,
                :source,
                :ip_address,
                :user_agent,
                :country,
                :region,
                :city,
                :consent_status,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                source = VALUES(source),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                country = VALUES(country),
                region = VALUES(region),
                city = VALUES(city),
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
            'country' => $country,
            'region' => $region,
            'city' => $city,
            'consent_status' => $consentStatus,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateConsentStatus(TenantContext $tenant, int $signupId, string $status): void
    {
        $allowed = ['pending', 'confirmed', 'unsubscribed'];

        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid email signup consent status: {$status}");
        }

        $confirmedAt = $status === 'confirmed' ? 'CURRENT_TIMESTAMP' : 'confirmed_at';
        $unsubscribedAt = $status === 'unsubscribed' ? 'CURRENT_TIMESTAMP' : 'unsubscribed_at';

        $stmt = $this->pdo->prepare(
            "UPDATE email_signups
             SET consent_status = :status,
                 confirmed_at = {$confirmedAt},
                 unsubscribed_at = {$unsubscribedAt},
                 updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = :tenant_id
               AND id = :id"
        );

        $stmt->execute([
            'status' => $status,
            'tenant_id' => $tenant->tenantId,
            'id' => $signupId,
        ]);
    }

    public function latestForTenant(TenantContext $tenant, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM email_signups
             WHERE tenant_id = :tenant_id
             ORDER BY id DESC
             LIMIT :limit_count OFFSET :offset_count"
        );

        $stmt->bindValue('tenant_id', $tenant->tenantId, PDO::PARAM_INT);
        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_count', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

// End of file.
