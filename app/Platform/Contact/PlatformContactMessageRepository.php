<?php

declare(strict_types=1);

namespace App\Platform\Contact;

use App\Support\Uuid;
use PDO;

/**
 * Persists public ArtsFolio platform contact submissions.
 *
 * Platform contact messages intentionally use the existing contact_messages
 * table with a NULL tenant_id so the workflow mirrors tenant contact messages
 * without inventing a parallel support-ticket table.
 */
final class PlatformContactMessageRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(
        string $senderName,
        string $senderEmail,
        string $message,
        ?string $subject = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $country = null,
        ?string $region = null,
        ?string $city = null,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO contact_messages (
                uuid,
                tenant_id,
                sender_name,
                sender_email,
                subject,
                message,
                ip_address,
                user_agent,
                country,
                region,
                city
            ) VALUES (
                :uuid,
                NULL,
                :sender_name,
                :sender_email,
                :subject,
                :message,
                :ip_address,
                :user_agent,
                :country,
                :region,
                :city
            )"
        );

        $stmt->execute([
            'uuid' => Uuid::v4(),
            'sender_name' => trim($senderName),
            'sender_email' => strtolower(trim($senderEmail)),
            'subject' => $subject !== null && trim($subject) !== '' ? trim($subject) : null,
            'message' => $message,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'country' => $country,
            'region' => $region,
            'city' => $city,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $messageId, string $status): void
    {
        $allowed = ['new', 'read', 'archived', 'spam'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid platform contact message status: {$status}");
        }

        $stmt = $this->pdo->prepare(
            "UPDATE contact_messages
             SET status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id IS NULL
               AND id = :id"
        );
        $stmt->execute(['status' => $status, 'id' => $messageId]);
    }

    /**
     * Soft-deletes a platform contact by moving it into archived status.
     */
    public function archive(int $messageId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE contact_messages
             SET status = 'archived',
                 updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id IS NULL
               AND id = :id"
        );
        $stmt->execute(['id' => $messageId]);
    }

    /**
     * Permanently removes a platform contact message.
     */
    public function delete(int $messageId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM contact_messages WHERE tenant_id IS NULL AND id = :id');
        $stmt->execute(['id' => $messageId]);
    }
}

// End of file.
