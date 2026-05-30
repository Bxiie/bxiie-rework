<?php

declare(strict_types=1);

namespace App\Platform\Identity;

use PDO;

/**
 * Read/write helpers for admin user-management screens.
 *
 * This repository intentionally exposes only admin-facing user facts and password
 * mutation. Role assignment and invitation flows should remain separate so this
 * screen does not become an accidental entitlement editor.
 */
final class AdminUserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Returns tenant users with tenant role, membership, and last browser-session use.
     */
    public function tenantUsers(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                u.id,
                u.uuid,
                u.email,
                u.display_name,
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
             GROUP BY u.id, u.uuid, u.email, u.display_name, u.created_at, tm.status
             ORDER BY u.email"
        );

        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    /**
     * Returns platform-scoped admins/support users.
     */
    public function platformUsers(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                u.id,
                u.uuid,
                u.email,
                u.display_name,
                u.created_at,
                GROUP_CONCAT(DISTINCT r.slug ORDER BY r.slug SEPARATOR ', ') AS roles,
                MAX(us.created_at) AS last_login_at
             FROM users u
             JOIN role_assignments ra ON ra.user_id = u.id AND ra.tenant_id IS NULL
             JOIN roles r ON r.id = ra.role_id AND r.scope = 'platform'
             LEFT JOIN user_sessions us ON us.user_id = u.id
             GROUP BY u.id, u.uuid, u.email, u.display_name, u.created_at
             ORDER BY u.email"
        );

        return $stmt->fetchAll();
    }

    /**
     * Updates a local password hash for an existing user.
     */
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

    /**
     * Confirms that a user belongs to the tenant before tenant-admin mutation.
     */
    public function userBelongsToTenant(int $tenantId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM tenant_memberships
             WHERE tenant_id = :tenant_id AND user_id = :user_id
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Confirms that a user has at least one platform-scoped role.
     */
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
}

// End of file.
