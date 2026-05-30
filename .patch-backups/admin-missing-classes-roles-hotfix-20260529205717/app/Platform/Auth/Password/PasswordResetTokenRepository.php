<?php

declare(strict_types=1);

namespace App\Platform\Auth\Password;

use PDO;

/**
 * Persists and consumes hashed password reset tokens.
 */
final class PasswordResetTokenRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(int $userId, string $tokenHash, int $ttlSeconds = 3600): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO password_reset_tokens (
                user_id,
                token_hash,
                expires_at
            ) VALUES (
                :user_id,
                :token_hash,
                DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :ttl_seconds SECOND)
            )"
        );

        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'ttl_seconds' => $ttlSeconds,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findActiveByHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM password_reset_tokens
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
            "UPDATE password_reset_tokens
             SET consumed_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND consumed_at IS NULL"
        );

        $stmt->execute(['id' => $tokenId]);
    }
}

// End of file.
