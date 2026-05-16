<?php

declare(strict_types=1);

namespace App\Platform\Email;

use PDO;

/**
 * Persists outbound email requests for queued delivery.
 */
final class EmailOutboxRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function queue(
        string $recipientEmail,
        string $subject,
        string $bodyText,
        ?string $bodyHtml = null,
        ?string $recipientName = null,
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $templateKey = null,
        int $availableAfterSeconds = 0,
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO email_outbox (
                tenant_id,
                user_id,
                recipient_email,
                recipient_name,
                subject,
                body_text,
                body_html,
                template_key,
                available_at
            ) VALUES (
                :tenant_id,
                :user_id,
                :recipient_email,
                :recipient_name,
                :subject,
                :body_text,
                :body_html,
                :template_key,
                DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :available_after SECOND)
            )"
        );

        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'recipient_email' => strtolower(trim($recipientEmail)),
            'recipient_name' => $recipientName,
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'template_key' => $templateKey,
            'available_after' => $availableAfterSeconds,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function latest(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM email_outbox
             ORDER BY id DESC
             LIMIT :limit_count"
        );

        $stmt->bindValue('limit_count', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function markSent(int $emailId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE email_outbox
             SET status = 'sent',
                 sent_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        $stmt->execute(['id' => $emailId]);
    }

    public function markFailed(int $emailId, string $error): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE email_outbox
             SET status = 'failed',
                 failed_at = CURRENT_TIMESTAMP,
                 last_error = :last_error,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        $stmt->execute([
            'id' => $emailId,
            'last_error' => $error,
        ]);
    }
}

// End of file.
