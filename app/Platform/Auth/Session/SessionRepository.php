<?php

/**
 * Browser session persistence.
 */

declare(strict_types=1);

namespace App\Platform\Auth\Session;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Persists and reads browser sessions.
 */
final class SessionRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(
        string $sessionHash,
        int $userId,
        ?int $tenantId,
        ?string $ipAddress,
        ?string $userAgent,
        int $ttlSeconds = 1209600,
    ): int {
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->add(new DateInterval('PT' . max(1, $ttlSeconds) . 'S'))
            ->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            "INSERT INTO user_sessions (
                session_hash,
                user_id,
                tenant_id,
                ip_address,
                user_agent,
                expires_at
            ) VALUES (
                :session_hash,
                :user_id,
                :tenant_id,
                :ip_address,
                :user_agent,
                :expires_at
            )"
        );

        $stmt->execute([
            'session_hash' => $sessionHash,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findActiveByHash(string $sessionHash): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                s.*,
                s.user_id AS user_id,
                u.email,
                u.display_name
             FROM user_sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.session_hash = :session_hash
               AND s.revoked_at IS NULL
               AND s.expires_at > CURRENT_TIMESTAMP
             LIMIT 1"
        );

        $stmt->execute(['session_hash' => $sessionHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function revokeByHash(string $sessionHash): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_sessions
             SET revoked_at = CURRENT_TIMESTAMP
             WHERE session_hash = :session_hash
               AND revoked_at IS NULL"
        );

        $stmt->execute(['session_hash' => $sessionHash]);
    }

    public function revokeByUserId(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_sessions
             SET revoked_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id
               AND revoked_at IS NULL"
        );

        $stmt->execute(['user_id' => $userId]);
    }
}

// End of file.
