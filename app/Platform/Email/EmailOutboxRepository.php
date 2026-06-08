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
        $brandedBodies = $this->brandBodies($subject, $bodyText, $bodyHtml);
        $bodyText = $brandedBodies['body_text'];
        $bodyHtml = $brandedBodies['body_html'];

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


    /**
     * Ensures every queued email carries ArtsFolio identity, including older
     * hard-coded invite and notification senders that do not use template files.
     */
    /**
     * Produces plain-text and branded HTML bodies for outbox rows.
     *
     * The SMTP worker sends multipart/alternative when body_html is populated.
     */
    private function brandBodies(string $subject, string $bodyText): array
    {
        return BrandedEmail::render($subject, $bodyText);
    }

    public function claimNext(): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->query(
                "SELECT *
                 FROM email_outbox
                 WHERE status = 'queued'
                   AND available_at <= CURRENT_TIMESTAMP
                 ORDER BY available_at ASC, id ASC
                 LIMIT 1
                 FOR UPDATE"
            );

            $email = $stmt->fetch();

            if (!$email) {
                $this->pdo->commit();
                return null;
            }

            $update = $this->pdo->prepare(
                "UPDATE email_outbox
                 SET status = 'sending',
                     attempts = attempts + 1,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );

            $update->execute(['id' => $email['id']]);
            $this->pdo->commit();

            return $email;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
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

    public function requeueSendingOlderThanMinutes(int $minutes): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE email_outbox
             SET status = 'queued',
                 updated_at = CURRENT_TIMESTAMP
             WHERE status = 'sending'
               AND updated_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL :minutes MINUTE)"
        );

        $stmt->execute(['minutes' => $minutes]);

        return $stmt->rowCount();
    }
}

// End of file.
