<?php

/**
 * Platform and tenant user-management repository.
 */

declare(strict_types=1);

namespace App\Platform\Identity;

use PDO;

/**
 * Read/write helpers for admin user-management screens.
 */
final class AdminUserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function tenantUsers(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                u.id,
                u.uuid,
                u.email,
                u.display_name,
                COALESCE(u.status, 'active') AS user_status,
                u.created_at,
                tm.status AS membership_status,
                GROUP_CONCAT(DISTINCT r.slug ORDER BY r.slug SEPARATOR ', ') AS roles,
                MAX(us.created_at) AS last_login_at
             FROM tenant_memberships tm
             JOIN users u ON u.id = tm.user_id
             LEFT JOIN role_assignments ra ON ra.user_id = u.id AND ra.tenant_id = tm.tenant_id
             LEFT JOIN roles r ON r.id = ra.role_id AND r.scope = 'tenant'
             LEFT JOIN user_sessions us ON us.user_id = u.id
             WHERE tm.tenant_id = :tenant_id
             GROUP BY u.id, u.uuid, u.email, u.display_name, u.status, u.created_at, tm.status
             ORDER BY u.email"
        );

        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public function platformUsers(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                u.id,
                u.uuid,
                u.email,
                u.display_name,
                COALESCE(u.status, 'active') AS user_status,
                u.created_at,
                GROUP_CONCAT(DISTINCT r.slug ORDER BY r.slug SEPARATOR ', ') AS roles,
                MAX(us.created_at) AS last_login_at
             FROM users u
             JOIN role_assignments ra ON ra.user_id = u.id AND ra.tenant_id IS NULL
             JOIN roles r ON r.id = ra.role_id AND r.scope = 'platform'
             LEFT JOIN user_sessions us ON us.user_id = u.id
             GROUP BY u.id, u.uuid, u.email, u.display_name, u.status, u.created_at
             ORDER BY u.email"
        );

        return $stmt->fetchAll();
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET password_hash = :password_hash,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :user_id"
        );

        $stmt->execute(['password_hash' => $passwordHash, 'user_id' => $userId]);
    }

    public function setUserStatus(int $userId, string $status): void
    {
        if (!in_array($status, ['active', 'suspended', 'deleted'], true)) {
            throw new \InvalidArgumentException('Invalid user status.');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :user_id"
        );
        $stmt->execute(['status' => $status, 'user_id' => $userId]);

        if ($status !== 'active') {
            $this->revokeUserSessions($userId);
        }
    }

    public function userBelongsToTenant(int $tenantId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM tenant_memberships
             WHERE tenant_id = :tenant_id AND user_id = :user_id
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    public function userIsPlatformUser(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM role_assignments ra
             JOIN roles r ON r.id = ra.role_id AND r.scope = 'platform'
             WHERE ra.user_id = :user_id AND ra.tenant_id IS NULL
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    private function revokeUserSessions(int $userId): void
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
