<?php

/**
 * Browser session persistence.
 */

declare(strict_types=1);

namespace App\Platform\Auth\Session;

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
                DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :ttl_seconds SECOND)
            )"
        );

        $stmt->execute([
            'session_hash' => $sessionHash,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'ttl_seconds' => $ttlSeconds,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findActiveByHash(string $sessionHash): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, u.email, u.display_name, COALESCE(u.status, 'active') AS user_status
             FROM user_sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.session_hash = :session_hash
               AND COALESCE(u.status, 'active') = 'active'
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
