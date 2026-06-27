<?php

declare(strict_types=1);

namespace App\Platform\Email;

use InvalidArgumentException;
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
        $this->assertSendableEmail($templateKey, $tenantId, $userId, $recipientEmail);

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
                DATE_ADD(UTC_TIMESTAMP(), INTERVAL :available_after SECOND)
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
     * Produces plain-text and branded HTML bodies for outbox rows.
     *
     * The SMTP worker sends multipart/alternative when body_html is populated.
     * Existing explicit HTML bodies are accepted for signature compatibility,
     * but the central ArtsFolio shell remains the canonical branding source.
     */
    private function brandBodies(string $subject, string $bodyText, ?string $bodyHtml = null): array
    {
        return BrandedEmail::render($subject, $bodyText);
    }

    /**
     * Blocks production-dangerous lifecycle rows before they reach the outbox.
     *
     * User lifecycle emails must belong to a real user. A NULL user_id turns
     * smoke tests and template previews into deliverable production mail, which
     * caused repeated lifecycle.welcome messages to be sent to platform inboxes.
     */
    private function assertSendableEmail(?string $templateKey, ?int $tenantId, ?int $userId, string $recipientEmail): void
    {
        $normalizedTemplateKey = strtolower(trim((string) $templateKey));
        $normalizedRecipient = strtolower(trim($recipientEmail));

        if (str_starts_with($normalizedTemplateKey, 'lifecycle.') && $userId === null) {
            throw new InvalidArgumentException(
                "Refusing to queue {$normalizedTemplateKey} for {$normalizedRecipient}: lifecycle email requires user_id."
            );
        }

        if (str_starts_with($normalizedTemplateKey, 'lifecycle.') && $tenantId === null) {
            throw new InvalidArgumentException(
                "Refusing to queue {$normalizedTemplateKey} for {$normalizedRecipient}: lifecycle email requires tenant_id."
            );
        }
    }

    public function claimNext(): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->query(
                "SELECT *
                 FROM email_outbox
                 WHERE status = 'queued'
                   AND available_at <= UTC_TIMESTAMP()
                 ORDER BY available_at ASC, id ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED"
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
                     updated_at = UTC_TIMESTAMP()
                 WHERE id = :id
                   AND status = 'queued'"
            );

            $update->execute(['id' => $email['id']]);
            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return null;
            }
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
                 sent_at = UTC_TIMESTAMP(),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id"
        );

        $stmt->execute(['id' => $emailId]);
    }

    public function markFailed(int $emailId, string $error): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE email_outbox
             SET status = 'failed',
                 failed_at = UTC_TIMESTAMP(),
                 last_error = :last_error,
                 updated_at = UTC_TIMESTAMP()
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
                 last_error = CONCAT(
                     COALESCE(NULLIF(last_error, ''), ''),
                     CASE WHEN COALESCE(last_error, '') = '' THEN '' ELSE '\n' END,
                     'Recovered stale sending email at ', UTC_TIMESTAMP(), '.'
                 ),
                 updated_at = UTC_TIMESTAMP()
             WHERE status = 'sending'
               AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :minutes MINUTE)"
        );

        $stmt->execute(['minutes' => $minutes]);

        return $stmt->rowCount();
    }
}

// End of file.
