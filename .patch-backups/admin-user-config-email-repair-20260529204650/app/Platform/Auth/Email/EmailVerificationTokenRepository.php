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

    public function create(int $userId, string $email, string $tokenHash, int $ttlSeconds = 86400): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO email_verification_tokens (
                user_id,
                email,
                token_hash,
                expires_at
            ) VALUES (
                :user_id,
                :email,
                :token_hash,
                DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :ttl_seconds SECOND)
            )"
        );

        $stmt->execute([
            'user_id' => $userId,
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
