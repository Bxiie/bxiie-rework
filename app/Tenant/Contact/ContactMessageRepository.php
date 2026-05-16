<?php

declare(strict_types=1);

namespace App\Tenant\Contact;

use App\Platform\Tenancy\TenantContext;
use PDO;

/**
 * Persists tenant public contact form messages.
 */
final class ContactMessageRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(
        TenantContext $tenant,
        string $senderName,
        string $senderEmail,
        string $message,
        ?string $subject = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO contact_messages (
                tenant_id,
                sender_name,
                sender_email,
                subject,
                message,
                ip_address,
                user_agent
            ) VALUES (
                :tenant_id,
                :sender_name,
                :sender_email,
                :subject,
                :message,
                :ip_address,
                :user_agent
            )"
        );

        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'sender_name' => trim($senderName),
            'sender_email' => strtolower(trim($senderEmail)),
            'subject' => $subject ? trim($subject) : null,
            'message' => $message,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function latestForTenant(TenantContext $tenant, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM contact_messages
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
