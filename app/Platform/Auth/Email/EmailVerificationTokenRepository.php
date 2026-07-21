<?php

declare(strict_types=1);

namespace App\Platform\Auth\Email;

use PDO;

/**
 * Persists and consumes hashed email verification tokens.
 */
final class EmailVerificationTokenRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(int $userId, string $email, string $tokenHash, ?int $tenantId = null, int $ttlSeconds = 86400): int
    {
        if ($tenantId !== null && $tenantId <= 0) {
            throw new \InvalidArgumentException('tenantId must be positive when supplied.');
        }

        $deleteSql = $tenantId === null
            ? 'DELETE FROM email_verification_tokens WHERE user_id = :user_id AND tenant_id IS NULL AND consumed_at IS NULL'
            : 'DELETE FROM email_verification_tokens WHERE user_id = :user_id AND tenant_id = :tenant_id AND consumed_at IS NULL';
        $delete = $this->pdo->prepare($deleteSql);
        $deleteParams = ['user_id' => $userId];
        if ($tenantId !== null) {
            $deleteParams['tenant_id'] = $tenantId;
        }
        $delete->execute($deleteParams);

        $stmt = $this->pdo->prepare(
            "INSERT INTO email_verification_tokens (
                user_id,
                tenant_id,
                email,
                token_hash,
                expires_at
            ) VALUES (
                :user_id,
                :tenant_id,
                :email,
                :token_hash,
                DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :ttl_seconds SECOND)
            )"
        );

        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'email' => strtolower(trim($email)),
            'token_hash' => $tokenHash,
            'ttl_seconds' => $ttlSeconds,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findActiveByHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM email_verification_tokens
             WHERE token_hash = :token_hash
               AND consumed_at IS NULL
               AND expires_at > CURRENT_TIMESTAMP
             LIMIT 1"
        );

        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function consume(int $tokenId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE email_verification_tokens
             SET consumed_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND consumed_at IS NULL"
        );

        $stmt->execute(['id' => $tokenId]);
    }
}

// End of file.
